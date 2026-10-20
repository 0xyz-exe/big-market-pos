<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_updates_balance_and_appends_a_traceable_movement(): void
    {
        [$branch, $product, $user] = $this->stockSetup();

        $balance = app(InventoryAdjustmentService::class)->adjust(
            $branch,
            $product,
            '5.25',
            'Stok awal hasil hitung fisik',
            $user,
            'opening_count',
            17,
        );

        $this->assertSame('5.250', $balance->quantity_on_hand);
        $movement = InventoryMovement::query()->sole();
        $this->assertSame('5.250', $movement->quantity_delta);
        $this->assertSame('5.250', $movement->quantity_after);
        $this->assertSame('adjustment_in', $movement->movement_type);
        $this->assertSame($user->id, $movement->user_id);
        $this->assertSame('opening_count', $movement->source_type);
        $this->assertSame(17, $movement->source_id);
    }

    public function test_decrease_records_signed_quantity_and_keeps_exact_three_decimal_precision(): void
    {
        [$branch, $product, $user] = $this->stockSetup();
        $service = app(InventoryAdjustmentService::class);
        $service->adjust($branch, $product, '5', 'Stok awal', $user);

        $balance = $service->adjust($branch, $product, '-1.257', 'Barang rusak', $user);

        $this->assertSame('3.743', $balance->quantity_on_hand);
        $this->assertSame('-1.257', InventoryMovement::query()->latest('id')->value('quantity_delta'));
        $this->assertSame('adjustment_out', InventoryMovement::query()->latest('id')->value('movement_type'));
    }

    public function test_adjustment_cannot_create_negative_stock_and_rolls_back(): void
    {
        [$branch, $product, $user] = $this->stockSetup();
        $balance = InventoryBalance::query()->sole();
        $balance->update(['quantity_on_hand' => '1.000']);

        try {
            app(InventoryAdjustmentService::class)->adjust($branch, $product, '-2', 'Koreksi', $user);
            $this->fail('Expected negative stock adjustment to be rejected.');
        } catch (InsufficientStockException) {
            $this->assertSame('1.000', $balance->fresh()->quantity_on_hand);
            $this->assertSame(0, InventoryMovement::query()->count());
        }
    }

    public function test_adjustment_requires_branch_access_and_inventory_permission(): void
    {
        [$branch, $product] = $this->stockSetup();
        $unauthorized = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(InventoryAdjustmentService::class)->adjust($branch, $product, '1', 'Koreksi', $unauthorized);
    }

    public function test_adjustment_rejects_a_balance_above_the_decimal_column_limit(): void
    {
        [$branch, $product, $user] = $this->stockSetup();
        InventoryBalance::query()->sole()->update(['quantity_on_hand' => '99999999999.999']);

        try {
            app(InventoryAdjustmentService::class)->adjust($branch, $product, '0.001', 'Tambahan stok', $user);
            $this->fail('Expected a balance beyond DECIMAL precision to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('batas kolom', $exception->getMessage());
            $this->assertSame('99999999999.999', InventoryBalance::query()->sole()->quantity_on_hand);
            $this->assertSame(0, InventoryMovement::query()->count());
        }
    }

    public function test_quantity_and_source_inputs_are_validated_before_mutation(): void
    {
        [$branch, $product, $user] = $this->stockSetup();
        $service = app(InventoryAdjustmentService::class);

        try {
            $service->adjust($branch, $product, '1.0001', 'Koreksi', $user);
            $this->fail('Expected quantity precision validation to fail.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('maksimal tiga digit pecahan', $exception->getMessage());
        }

        $this->assertSame('0.000', InventoryBalance::query()->sole()->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    /** @return array{Branch, Product, User} */
    private function stockSetup(): array
    {
        $branch = Branch::create(['code' => 'JKT-STK', 'name' => 'Cabang Stok']);
        $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'tea-001',
            'barcode' => '899000001',
            'name' => 'Teh Botol',
            'purchase_cost' => '1000.00',
            'default_selling_price' => '1500.00',
        ]);
        $product->branches()->attach($branch, ['selling_price' => '1500.00']);
        InventoryBalance::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '0.000',
        ]);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'inventory-staff', 'label' => 'Petugas persediaan']);
        $permission = Permission::create(['name' => 'inventory.adjust', 'label' => 'Menyesuaikan stok']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $product, $user];
    }
}
