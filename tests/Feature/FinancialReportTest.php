<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_report_subtracts_only_approved_expenses_for_the_active_branch_and_period(): void
    {
        [$branch, $user] = $this->authorizedUser('reports.financial');
        $otherBranch = Branch::query()->create(['code' => 'FIN-OTHER', 'name' => 'Cabang Keuangan Lain']);
        $product = Product::query()->create([
            'sku' => 'FIN-001',
            'name' => 'Produk Laporan Keuangan',
            'purchase_cost' => '300.00',
            'default_selling_price' => '500.00',
        ]);
        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'cashier_id' => $user->id,
            'transaction_number' => 'BM-FIN-001',
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', 'BM-FIN-001'),
            'status' => 'completed',
            'payment_method' => 'cash',
            'subtotal' => '500.00',
            'tax_total' => '55.00',
            'total' => '555.00',
            'amount_received' => '555.00',
            'change_due' => '0.00',
            'completed_at' => '2026-10-20 10:00:00',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => '1.000',
            'unit_price' => '500.00',
            'subtotal' => '500.00',
            'tax_rate' => '11.00',
            'tax_amount' => '55.00',
            'total' => '555.00',
            'unit_cost' => '300.00',
            'cost_total' => '300.00',
        ]);

        $category = ExpenseCategory::query()->create(['name' => 'Biaya Operasional', 'is_active' => true]);
        $this->createExpense($branch, $user, $category, 'Approved branch cost', '125.00', 'approved', '2026-10-20');
        $this->createExpense($branch, $user, $category, 'Pending branch cost', '900.00', 'pending', '2026-10-20');
        $this->createExpense($branch, $user, $category, 'Prior-period cost', '800.00', 'approved', '2026-10-19');
        $this->createExpense($otherBranch, $user, $category, 'Other-branch cost', '700.00', 'approved', '2026-10-20');
        $this->assertDatabaseHas('expenses', ['description' => 'Approved branch cost', 'status' => 'approved', 'branch_id' => $branch->id]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.financial', ['from' => '2026-10-20', 'to' => '2026-10-20']))
            ->assertOk()
            ->assertViewHas('financialSummary.expense_total', '125.00')
            ->assertViewHas('financialSummary.approved_expense_count', 1)
            ->assertViewHas('financialSummary.gross_profit', '200.00')
            ->assertViewHas('financialSummary.contribution_after_expenses', '75.00')
            ->assertViewHas('dailyExpenseTotals', fn (array $days): bool => $days[0]['date'] === '2026-10-20' && $days[0]['amount'] === '125.00');
    }

    public function test_sales_report_permission_alone_does_not_grant_financial_expense_report_access(): void
    {
        [$branch, $user] = $this->authorizedUser('reports.sales');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.financial'))
            ->assertForbidden();
    }

    private function authorizedUser(string $permissionName): array
    {
        $branch = Branch::query()->create(['code' => 'FIN-'.strtoupper(Str::random(5)), 'name' => 'Cabang Keuangan']);
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'fin-'.strtolower(Str::random(8)), 'label' => 'Laporan keuangan']);
        $permission = Permission::query()->create(['name' => $permissionName, 'label' => $permissionName]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $user];
    }

    private function createExpense(
        Branch $branch,
        User $creator,
        ExpenseCategory $category,
        string $description,
        string $amount,
        string $status,
        string $date,
    ): Expense {
        return Expense::query()->create([
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'created_by' => $creator->id,
            'decided_by' => $status === 'pending' ? null : $creator->id,
            'expense_date' => $date,
            'description' => $description,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => $status,
            'decided_at' => $status === 'pending' ? null : now(),
        ]);
    }
}
