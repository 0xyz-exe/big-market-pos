<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_can_be_received_in_parts_once_per_receipt_and_updates_branch_weighted_cost(): void
    {
        [$branch, $product, $user, $supplier] = $this->purchasingSetup();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'notes' => 'Pengadaan mingguan',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity_ordered' => '5.000',
                    'unit_cost' => '400.00',
                ]],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->sole();
        $line = $order->items()->sole();
        $receiptKey = '21212121-2121-4212-8212-212121212121';
        $payload = [
            'idempotency_key' => $receiptKey,
            'items' => [['purchase_order_item_id' => $line->id, 'quantity' => '3.000']],
        ];

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('purchases.receive', $order), $payload)
            ->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'partially_received']);
        $this->assertDatabaseHas('inventory_balances', ['branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => '5.000']);
        $this->assertDatabaseHas('branch_products', ['branch_id' => $branch->id, 'product_id' => $product->id, 'purchase_cost' => '360.00']);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('purchases.receive', $order), $payload)
            ->assertRedirect();
        $this->assertSame(1, $order->receipts()->where('idempotency_key', $receiptKey)->count());
        $this->assertDatabaseHas('inventory_balances', ['branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => '5.000']);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('purchases.receive', $order), [
                'idempotency_key' => '22222222-2222-4222-8222-222222222222',
                'items' => [['purchase_order_item_id' => $line->id, 'quantity' => '2.000']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'received']);
        $this->assertSame('7.000', InventoryBalance::query()->sole()->quantity_on_hand);
        $this->assertSame('371.43', DB::table('branch_products')->value('purchase_cost'));
        $this->assertSame(2, InventoryMovement::query()->where('movement_type', 'purchase_in')->count());
    }

    private function purchasingSetup(): array
    {
        $suffix = strtoupper(Str::random(6));
        $branch = Branch::query()->create(['code' => 'BUY-'.$suffix, 'name' => 'Cabang Pembelian']);
        $supplier = Supplier::query()->create(['code' => 'SUP-'.$suffix, 'name' => 'Pemasok Uji']);
        $product = Product::query()->create([
            'sku' => 'BUY-'.$suffix,
            'name' => 'Produk Pengadaan',
            'purchase_cost' => '300.00',
            'default_selling_price' => '700.00',
        ]);
        $product->branches()->attach($branch, ['selling_price' => '700.00', 'purchase_cost' => '300.00', 'is_available' => true]);
        InventoryBalance::query()->create(['branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => '2.000']);
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'purchaser-'.strtolower($suffix), 'label' => 'Petugas Pembelian']);
        $permissions = collect(['purchases.manage', 'purchases.receive'])->map(fn (string $name) => Permission::query()->firstOrCreate(['name' => $name], ['label' => $name]));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $product, $user, $supplier];
    }
}
