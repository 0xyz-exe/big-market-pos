<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\SaleCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class DiscountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_discount_is_recorded_and_tax_is_calculated_after_discount(): void
    {
        [$branch, $product, $cashier] = $this->discountSetup(true);

        $sale = app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '1']],
            '1000.00',
            '19191919-1919-4191-8191-191919191919',
            '10.00',
            'Promo akhir pekan',
        );

        $this->assertSame('500.00', $sale->subtotal);
        $this->assertSame('50.00', $sale->discount_amount);
        $this->assertSame('10.00', $sale->discount_percent);
        $this->assertSame('Promo akhir pekan', $sale->discount_reason);
        $this->assertSame('49.50', $sale->tax_total);
        $this->assertSame('499.50', $sale->total);
        $this->assertSame('50.00', $sale->items->sole()->discount_amount);
    }

    public function test_cashier_without_discount_permission_cannot_apply_a_discount(): void
    {
        [$branch, $product, $cashier] = $this->discountSetup(false);

        $this->expectException(AuthorizationException::class);
        app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '1']],
            '1000.00',
            '20202020-2020-4202-8202-202020202020',
            '5.00',
            'Diskon tanpa izin',
        );
    }

    private function discountSetup(bool $allowDiscount): array
    {
        $suffix = strtoupper(Str::random(6));
        $branch = Branch::query()->create(['code' => 'DISC-'.$suffix, 'name' => 'Cabang Diskon']);
        $category = Category::query()->create(['name' => 'Promo '.$suffix, 'slug' => 'promo-'.$suffix]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'DISC-'.$suffix,
            'name' => 'Produk Diskon',
            'purchase_cost' => '300.00',
            'default_selling_price' => '500.00',
            'tax_rate' => '11.00',
        ]);
        $product->branches()->attach($branch, ['selling_price' => '500.00', 'is_available' => true]);
        InventoryBalance::query()->create(['branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => '5.000']);
        $cashier = User::factory()->create();
        $role = Role::query()->create(['name' => 'discount-'.strtolower($suffix), 'label' => 'Kasir Diskon']);
        $permissionNames = ['sales.create', 'shifts.manage'];
        if ($allowDiscount) {
            $permissionNames[] = 'sales.discount';
        }
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::query()->firstOrCreate(['name' => $name], ['label' => $name]));
        $role->permissions()->attach($permissions);
        $cashier->roles()->attach($role);
        $cashier->branches()->attach($branch);
        CashierShift::query()->create([
            'branch_id' => $branch->id,
            'user_id' => $cashier->id,
            'open_user_id' => $cashier->id,
            'status' => 'open',
            'opening_cash' => '0.00',
            'opened_at' => now($branch->timezone),
        ]);

        return [$branch, $product, $cashier];
    }
}
