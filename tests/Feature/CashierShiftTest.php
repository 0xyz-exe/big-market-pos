<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\SaleCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_closing_reconciles_cash_sales_and_records_variance(): void
    {
        [$branch, $product, $cashier] = $this->shiftSetup();

        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('shifts.open'), ['opening_cash' => '10000.00'])
            ->assertRedirect(route('shifts.index'));

        $shift = $cashier->shifts()->sole();
        app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '2']],
            '2000.00',
            '17171717-1717-4171-8171-171717171717',
        );

        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('shifts.close', $shift), ['closing_cash' => '11100.00'])
            ->assertRedirect(route('shifts.index'));

        $this->assertDatabaseHas('cashier_shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'opening_cash' => '10000.00',
            'expected_cash' => '11110.00',
            'closing_cash' => '11100.00',
            'variance' => '-10.00',
        ]);
    }

    public function test_cash_checkout_requires_an_open_shift(): void
    {
        [$branch, $product, $cashier] = $this->shiftSetup();

        try {
            app(SaleCheckoutService::class)->checkout(
                $branch,
                $cashier,
                [['product_id' => $product->id, 'quantity' => '1']],
                '1000.00',
                '18181818-1818-4181-8181-181818181818',
            );
            $this->fail('Expected checkout without an open shift to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('shift kasir', mb_strtolower($exception->getMessage()));
        }
    }

    public function test_cashier_cannot_close_another_users_shift(): void
    {
        [$branch, $product, $cashier] = $this->shiftSetup();
        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('shifts.open'), ['opening_cash' => '0.00'])
            ->assertRedirect(route('shifts.index'));
        $shift = $cashier->shifts()->sole();

        $other = User::factory()->create();
        $role = Role::query()->create(['name' => 'other-cashier-'.strtolower(Str::random(6)), 'label' => 'Kasir lain']);
        $permissions = collect(['shifts.manage'])->map(fn (string $name) => Permission::query()->firstOrCreate(['name' => $name], ['label' => $name]));
        $role->permissions()->attach($permissions);
        $other->roles()->attach($role);
        $other->branches()->attach($branch);

        $this->actingAs($other)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('shifts.close', $shift), ['closing_cash' => '0.00'])
            ->assertForbidden();
    }

    private function shiftSetup(): array
    {
        $branch = Branch::query()->create(['code' => 'SHIFT-'.strtoupper(Str::random(4)), 'name' => 'Cabang Shift']);
        $category = Category::query()->create(['name' => 'Barang Shift '.Str::random(4), 'slug' => 'barang-shift-'.Str::lower(Str::random(4))]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SHIFT-'.strtoupper(Str::random(5)),
            'name' => 'Produk Shift',
            'purchase_cost' => '300.00',
            'default_selling_price' => '500.00',
            'tax_rate' => '11.00',
        ]);
        $product->branches()->attach($branch, ['selling_price' => '500.00', 'is_available' => true]);
        InventoryBalance::query()->create(['branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => '5.000']);
        $cashier = User::factory()->create();
        $role = Role::query()->create(['name' => 'cashier-'.strtolower(Str::random(8)), 'label' => 'Kasir']);
        $permissions = collect(['sales.create', 'shifts.manage'])->map(fn (string $name) => Permission::query()->firstOrCreate(['name' => $name], ['label' => $name]));
        $role->permissions()->attach($permissions);
        $cashier->roles()->attach($role);
        $cashier->branches()->attach($branch);

        return [$branch, $product, $cashier];
    }
}
