<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_submit_and_approve_a_branch_expense(): void
    {
        [$branch, $user] = $this->expenseUser(['expenses.manage', 'expenses.approve']);
        $category = ExpenseCategory::query()->create(['name' => 'Listrik', 'is_active' => true]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('expenses.create'))
            ->assertOk()
            ->assertSee('Catat pengeluaran')
            ->assertSee('Listrik');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'description' => 'Tagihan listrik toko',
                'amount' => '125000.50',
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('status');

        $expense = Expense::query()->sole();
        $this->assertSame('pending', $expense->status);
        $this->assertSame('125000.50', $expense->amount);
        $this->assertSame($branch->id, $expense->branch_id);
        $this->assertSame($user->id, $expense->created_by);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.approve', $expense))
            ->assertSessionHasErrors('expense');
        $this->assertSame('pending', $expense->fresh()->status);

        [, $approver] = $this->expenseUser(['expenses.approve']);
        $approver->branches()->attach($branch);
        $this->actingAs($approver)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.approve', $expense))
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'approved',
            'decided_by' => $approver->id,
        ]);
    }

    public function test_expense_listing_is_limited_to_the_active_branch(): void
    {
        [$branch, $user] = $this->expenseUser(['expenses.manage']);
        $otherBranch = Branch::query()->create(['code' => 'EXP-OTHER', 'name' => 'Cabang Expense Lain']);
        $category = ExpenseCategory::query()->create(['name' => 'Transportasi', 'is_active' => true]);
        foreach ([[$branch, 'Biaya cabang sendiri'], [$otherBranch, 'Biaya cabang lain']] as [$expenseBranch, $description]) {
            Expense::query()->create([
                'branch_id' => $expenseBranch->id,
                'expense_category_id' => $category->id,
                'created_by' => $user->id,
                'expense_date' => now()->toDateString(),
                'description' => $description,
                'amount' => '1000.00',
                'payment_method' => 'cash',
                'status' => 'pending',
            ]);
        }

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('Biaya cabang sendiri')
            ->assertDontSee('Biaya cabang lain');
    }

    public function test_users_without_expense_permission_cannot_list_or_submit_expenses(): void
    {
        $branch = Branch::query()->create(['code' => 'EXP-NONE', 'name' => 'Cabang Tanpa Expense']);
        $user = User::factory()->create();
        $user->branches()->attach($branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('expenses.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.store'), [])
            ->assertForbidden();
    }

    public function test_approved_expense_is_immutable_and_rejection_requires_a_reason(): void
    {
        [$branch, $user] = $this->expenseUser(['expenses.manage', 'expenses.approve']);
        $category = ExpenseCategory::query()->create(['name' => 'Perawatan', 'is_active' => true]);
        $expense = Expense::query()->create([
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'created_by' => $user->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Perawatan pendingin',
            'amount' => '250000.00',
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.reject', $expense), ['decision_note' => ''])
            ->assertSessionHasErrors('decision_note');

        [, $approver] = $this->expenseUser(['expenses.approve']);
        $approver->branches()->attach($branch);
        $this->actingAs($approver)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.approve', $expense))
            ->assertRedirect(route('expenses.index'));

        $this->expectException(\LogicException::class);
        $expense->fresh()->update(['amount' => '1.00']);
    }

    public function test_expense_approval_cannot_cross_the_active_branch(): void
    {
        [$branch, $user] = $this->expenseUser(['expenses.manage', 'expenses.approve']);
        $otherBranch = Branch::query()->create(['code' => 'EXP-PRIVATE', 'name' => 'Cabang Privat']);
        $category = ExpenseCategory::query()->create(['name' => 'Konsumsi', 'is_active' => true]);
        $expense = Expense::query()->create([
            'branch_id' => $otherBranch->id,
            'expense_category_id' => $category->id,
            'created_by' => $user->id,
            'expense_date' => now()->toDateString(),
            'description' => 'Biaya cabang terpisah',
            'amount' => '75000.00',
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.approve', $expense))
            ->assertNotFound();

        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_receipt_upload_is_private_and_download_is_branch_scoped(): void
    {
        Storage::fake('local');
        [$branch, $user] = $this->expenseUser(['expenses.manage']);
        $otherBranch = Branch::query()->create(['code' => 'EXP-FILE', 'name' => 'Cabang Berkas Lain']);
        $user->branches()->attach($otherBranch);
        $category = ExpenseCategory::query()->create(['name' => 'Tagihan', 'is_active' => true]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'description' => 'Tagihan dengan bukti',
                'amount' => '42500.00',
                'payment_method' => 'bank_transfer',
                'receipt' => UploadedFile::fake()->create('tagihan.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect(route('expenses.index'));

        $expense = Expense::query()->sole();
        $this->assertNotNull($expense->receipt_path);
        Storage::disk('local')->assertExists($expense->receipt_path);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('expenses.receipt', $expense))
            ->assertOk()
            ->assertDownload('tagihan.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $otherBranch->id])
            ->get(route('expenses.receipt', $expense))
            ->assertNotFound();
    }

    /** @return array{Branch, User} */
    private function expenseUser(array $permissions): array
    {
        $branch = Branch::query()->create(['code' => 'EXP-'.strtoupper(Str::random(5)), 'name' => 'Cabang Pengeluaran']);
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'expense-'.strtolower(Str::random(8)), 'label' => 'Pengelola pengeluaran']);
        $permissionIds = collect($permissions)->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
        ], [
            'label' => $name,
        ])->id);
        $role->permissions()->attach($permissionIds);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $user];
    }
}
