<?php

namespace Tests\Feature;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\SaleCheckoutService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class SaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_checkout_uses_server_price_tax_and_decrements_stock_once(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();

        $sale = app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '2.000', 'unit_price' => '0.01']],
            '2000.00',
            '11111111-1111-4111-8111-111111111111',
        );

        $item = SaleItem::query()->sole();
        $movement = InventoryMovement::query()->where('source_type', 'sale')->sole();
        $this->assertSame('1110.00', $sale->total);
        $this->assertSame('2000.00', $sale->amount_received);
        $this->assertSame('890.00', $sale->change_due);
        $this->assertSame('500.00', $item->unit_price);
        $this->assertSame('110.00', $item->tax_amount);
        $this->assertSame('600.00', $item->cost_total);
        $this->assertSame('3.000', InventoryBalance::query()->sole()->quantity_on_hand);
        $this->assertSame('-2.000', $movement->quantity_delta);
        $this->assertSame($sale->id, $movement->source_id);
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_completed_sale_and_line_snapshots_cannot_be_changed_or_deleted(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $sale = app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '1']],
            '1000.00',
            '12121212-1212-4121-8121-121212121212',
        );
        $item = $sale->items->sole();

        try {
            $sale->update(['total' => '0.00']);
            $this->fail('Expected a completed sale update to be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => '555.00']);
        }

        try {
            $item->delete();
            $this->fail('Expected a sale item deletion to be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('sale_items', ['id' => $item->id]);
        }

        try {
            $sale->delete();
            $this->fail('Expected a completed sale deletion to be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        }
    }

    public function test_sale_rejects_a_cost_snapshot_that_exceeds_database_precision(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $product->update(['purchase_cost' => '999999999999.99']);

        try {
            app(SaleCheckoutService::class)->checkout(
                $branch,
                $cashier,
                [['product_id' => $product->id, 'quantity' => '2']],
                '2000.00',
                '13131313-1313-4131-8131-131313131313',
            );
            $this->fail('Expected an out-of-range cost snapshot to be rejected before persistence.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('batas nominal', $exception->getMessage());
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame('5.000', InventoryBalance::query()->sole()->quantity_on_hand);
        }
    }

    public function test_insufficient_stock_rolls_back_sale_items_and_movements(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();

        try {
            app(SaleCheckoutService::class)->checkout(
                $branch,
                $cashier,
                [['product_id' => $product->id, 'quantity' => '6']],
                '5000.00',
                '22222222-2222-4222-8222-222222222222',
            );
            $this->fail('Expected insufficient stock to reject the checkout.');
        } catch (InsufficientStockException) {
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, SaleItem::query()->count());
            $this->assertSame(0, InventoryMovement::query()->count());
            $this->assertSame('5.000', InventoryBalance::query()->sole()->quantity_on_hand);
        }
    }

    public function test_replayed_idempotency_key_returns_the_sale_without_deducting_stock_twice(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $service = app(SaleCheckoutService::class);
        $items = [['product_id' => $product->id, 'quantity' => '2']];
        $key = '33333333-3333-4333-8333-333333333333';

        $first = $service->checkout($branch, $cashier, $items, '2000.00', $key);
        $replay = $service->checkout($branch, $cashier, $items, '2000.00', $key);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame('3.000', InventoryBalance::query()->sole()->quantity_on_hand);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_reusing_an_idempotency_key_with_a_different_cart_is_rejected(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $service = app(SaleCheckoutService::class);
        $key = '44444444-4444-4444-8444-444444444444';
        $service->checkout($branch, $cashier, [['product_id' => $product->id, 'quantity' => '1']], '1000.00', $key);

        $this->expectException(IdempotencyConflictException::class);
        $service->checkout($branch, $cashier, [['product_id' => $product->id, 'quantity' => '2']], '2000.00', $key);
    }

    public function test_cash_shortfall_is_rejected_without_persisting_a_sale(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();

        try {
            app(SaleCheckoutService::class)->checkout(
                $branch,
                $cashier,
                [['product_id' => $product->id, 'quantity' => '1']],
                '500.00',
                '55555555-5555-4555-8555-555555555555',
            );
            $this->fail('Expected a cash shortfall to reject the checkout.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak mencukupi', $exception->getMessage());
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame('5.000', InventoryBalance::query()->sole()->quantity_on_hand);
        }
    }

    public function test_user_without_sales_permission_cannot_checkout(): void
    {
        [$branch, $product] = $this->checkoutSetup();
        $unassignedUser = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(SaleCheckoutService::class)->checkout(
            $branch,
            $unassignedUser,
            [['product_id' => $product->id, 'quantity' => '1']],
            '1000.00',
            '66666666-6666-4666-8666-666666666666',
        );
    }

    public function test_pos_page_renders_for_an_authorized_cashier(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();

        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Kasir POS')
            ->assertSee('Selesaikan penjualan tunai')
            ->assertSee(route('pos.products.search'));
    }

    public function test_http_checkout_rejects_client_submitted_prices(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();

        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->postJson(route('pos.checkout'), [
                'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '0.01']],
                'amount_received' => '1000.00',
                'idempotency_key' => '99999999-9999-4999-8999-999999999999',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('5.000', InventoryBalance::query()->sole()->quantity_on_hand);
    }

    public function test_sales_history_is_restricted_to_the_active_branch(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $otherBranch = Branch::query()->create(['code' => 'BDG-HIST', 'name' => 'Cabang Riwayat Lain']);

        foreach ([[$branch, 'BM-LOCAL-HISTORY'], [$otherBranch, 'BM-OTHER-HISTORY']] as [$saleBranch, $number]) {
            Sale::query()->create([
                'branch_id' => $saleBranch->id,
                'cashier_id' => $cashier->id,
                'transaction_number' => $number,
                'idempotency_key' => (string) Str::uuid(),
                'request_fingerprint' => str_repeat('a', 64),
                'status' => 'completed',
                'payment_method' => 'cash',
                'subtotal' => '500.00',
                'tax_total' => '55.00',
                'total' => '555.00',
                'amount_received' => '1000.00',
                'change_due' => '445.00',
                'completed_at' => now(),
            ]);
        }

        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('BM-LOCAL-HISTORY')
            ->assertDontSee('BM-OTHER-HISTORY');
    }

    public function test_sales_history_search_is_case_insensitive_when_database_like_is_case_sensitive(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $sale = app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '1']],
            '1000.00',
            '14141414-1414-4141-8141-141414141414',
        );
        $usesSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($usesSqlite) {
            DB::statement('PRAGMA case_sensitive_like = ON');
        }

        try {
            $this->actingAs($cashier)
                ->withSession(['active_branch_id' => $branch->id])
                ->get(route('sales.index', ['q' => mb_strtolower($sale->transaction_number)]))
                ->assertOk()
                ->assertSee($sale->transaction_number);
        } finally {
            if ($usesSqlite) {
                DB::statement('PRAGMA case_sensitive_like = OFF');
            }
        }
    }

    public function test_pos_search_finds_products_without_case_sensitive_like_behavior(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $usesSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($usesSqlite) {
            DB::statement('PRAGMA case_sensitive_like = ON');
        }

        try {
            $this->actingAs($cashier)
                ->withSession(['active_branch_id' => $branch->id])
                ->getJson(route('pos.products.search', ['q' => 'bIsKuIt']))
                ->assertOk()
                ->assertJsonPath('0.id', $product->id);
        } finally {
            if ($usesSqlite) {
                DB::statement('PRAGMA case_sensitive_like = OFF');
            }
        }
    }

    public function test_pos_search_is_scoped_to_the_active_branch(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $otherBranch = Branch::query()->create(['code' => 'BDG-POS', 'name' => 'Cabang Lain']);
        $otherProduct = Product::query()->create([
            'sku' => 'POS-002',
            'name' => 'Biskuit Cabang Lain',
            'purchase_cost' => '100.00',
            'default_selling_price' => '200.00',
        ]);
        $otherProduct->branches()->attach($otherBranch, ['selling_price' => '200.00', 'is_available' => true]);
        InventoryBalance::query()->create([
            'branch_id' => $otherBranch->id,
            'product_id' => $otherProduct->id,
            'quantity_on_hand' => '8.000',
        ]);

        $response = $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->getJson(route('pos.products.search', ['q' => 'Biskuit']));

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $product->id);
        $this->assertSame(0, bccomp($response->json('0.selling_price'), '500.00', 2));
        $this->assertSame(0, bccomp($response->json('0.stock'), '5.000', 3));
    }

    public function test_cash_checkout_route_persists_sale_and_displays_its_receipt(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();

        $response = $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('pos.checkout'), [
                'items' => [['product_id' => $product->id, 'quantity' => '2']],
                'amount_received' => '2000.00',
                'idempotency_key' => '77777777-7777-4777-8777-777777777777',
            ]);

        $sale = Sale::query()->sole();
        $response->assertRedirect(route('sales.receipt', $sale));
        $this->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertSee($sale->transaction_number)
            ->assertSee('Rp 1.110');
        $this->assertSame('3.000', InventoryBalance::query()->sole()->quantity_on_hand);
    }

    public function test_cashier_cannot_view_a_receipt_from_another_branch(): void
    {
        [$branch, $product, $cashier] = $this->checkoutSetup();
        $sale = app(SaleCheckoutService::class)->checkout(
            $branch,
            $cashier,
            [['product_id' => $product->id, 'quantity' => '1']],
            '1000.00',
            '88888888-8888-4888-8888-888888888888',
        );
        $otherBranch = Branch::query()->create(['code' => 'BDG-REC', 'name' => 'Cabang Penerima']);
        $cashier->branches()->attach($otherBranch);

        $this->actingAs($cashier)
            ->withSession(['active_branch_id' => $otherBranch->id])
            ->get(route('sales.receipt', $sale))
            ->assertNotFound();
    }

    /** @return array{Branch, Product, User} */
    private function checkoutSetup(): array
    {
        $branch = Branch::query()->create(['code' => 'JKT-POS', 'name' => 'Cabang Kasir']);
        $category = Category::query()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'POS-001',
            'barcode' => '8991000001',
            'name' => 'Biskuit',
            'purchase_cost' => '300.00',
            'default_selling_price' => '500.00',
            'tax_rate' => '11.00',
        ]);
        $product->branches()->attach($branch, ['selling_price' => '500.00', 'is_available' => true]);
        InventoryBalance::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '5.000',
        ]);

        $cashier = User::factory()->create();
        $role = Role::query()->create(['name' => 'test-cashier', 'label' => 'Kasir Pengujian']);
        $permission = Permission::query()->create(['name' => 'sales.create', 'label' => 'Buat penjualan']);
        $viewPermission = Permission::query()->create(['name' => 'sales.view', 'label' => 'Lihat penjualan']);
        $shiftPermission = Permission::query()->firstOrCreate(['name' => 'shifts.manage'], ['label' => 'Kelola shift kasir']);
        $role->permissions()->attach([$permission->id, $viewPermission->id, $shiftPermission->id]);
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
