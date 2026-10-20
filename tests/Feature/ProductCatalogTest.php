<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_product_for_the_selected_branch_with_zero_initial_stock(): void
    {
        [$branch, $user] = $this->catalogManager();
        $otherBranch = Branch::create(['code' => 'JKT-OTR', 'name' => 'Cabang Lain']);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('products.store'), $this->validProduct([
                'sku' => ' tea-001 ',
                'branch_id' => $otherBranch->id,
            ]))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('status');

        $product = Product::query()->sole();
        $this->assertSame('TEA-001', $product->sku);
        $this->assertSame('1', (string) $product->branches()->whereKey($branch->id)->count());
        $this->assertSame('0', (string) $product->branches()->whereKey($otherBranch->id)->count());
        $this->assertTrue(bccomp((string) $product->branches()->first()->pivot->selling_price, '2750.00', 2) === 0);
        $this->assertSame('0.000', InventoryBalance::query()->sole()->quantity_on_hand);
    }

    public function test_product_search_and_inventory_are_scoped_to_selected_branch(): void
    {
        [$branch, $user] = $this->catalogManager();
        $otherBranch = Branch::create(['code' => 'BDG-01', 'name' => 'Bandung']);
        $this->createProduct('TEA-JKT', 'Teh Melati', $branch);
        $this->createProduct('TEA-BDG', 'Teh Melati Premium', $otherBranch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('products.index', ['q' => 'Teh']))
            ->assertOk()
            ->assertSee('Teh Melati')
            ->assertDontSee('Teh Melati Premium');
    }

    public function test_product_search_is_case_insensitive_when_database_like_is_case_sensitive(): void
    {
        [$branch, $user] = $this->catalogManager();
        $this->createProduct('TEA-JKT', 'Teh Melati', $branch);
        $usesSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($usesSqlite) {
            DB::statement('PRAGMA case_sensitive_like = ON');
        }

        try {
            $this->actingAs($user)
                ->withSession(['active_branch_id' => $branch->id])
                ->get(route('products.index', ['q' => 'tEh mElAtI']))
                ->assertOk()
                ->assertSee('Teh Melati');
        } finally {
            if ($usesSqlite) {
                DB::statement('PRAGMA case_sensitive_like = OFF');
            }
        }
    }

    public function test_product_creation_rejects_duplicate_sku_even_when_case_differs(): void
    {
        [$branch, $user] = $this->catalogManager();
        $this->createProduct('TEA-001', 'Teh Pertama', $branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('products.store'), $this->validProduct(['sku' => 'tea-001']))
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::query()->count());
    }

    public function test_staff_without_catalog_permission_cannot_view_or_create_products(): void
    {
        $branch = Branch::create(['code' => 'MLG-01', 'name' => 'Malang']);
        $user = User::factory()->create();
        $user->branches()->attach($branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('products.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('products.store'), $this->validProduct())
            ->assertForbidden();
    }

    public function test_invalid_prices_and_stock_threshold_do_not_create_product(): void
    {
        [$branch, $user] = $this->catalogManager();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('products.store'), $this->validProduct([
                'selling_price' => '-0.01',
                'minimum_stock' => '-1',
            ]))
            ->assertSessionHasErrors(['selling_price', 'minimum_stock']);

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, InventoryBalance::query()->count());
    }

    private function catalogManager(): array
    {
        $branch = Branch::create(['code' => 'JKT-CAT', 'name' => 'Cabang Katalog']);
        $user = User::factory()->create();
        $role = Role::create(['name' => 'catalog-manager', 'label' => 'Pengelola katalog']);
        $permission = Permission::create(['name' => 'products.manage', 'label' => 'Kelola katalog produk']);
        $viewPermission = Permission::create(['name' => 'products.view', 'label' => 'Lihat katalog produk']);
        $role->permissions()->attach([$permission->id, $viewPermission->id]);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $user];
    }

    private function createProduct(string $sku, string $name, Branch $branch): Product
    {
        $product = Product::query()->create([
            'sku' => $sku,
            'name' => $name,
            'unit_of_measure' => 'botol',
            'purchase_cost' => '2000.00',
            'default_selling_price' => '2750.00',
            'tax_rate' => '0.00',
            'is_active' => true,
        ]);
        $product->branches()->attach($branch, [
            'selling_price' => '2750.00',
            'minimum_stock' => '3.000',
            'is_available' => true,
        ]);
        InventoryBalance::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '6.000',
        ]);

        return $product;
    }

    private function validProduct(array $overrides = []): array
    {
        return array_replace([
            'sku' => 'TEA-002',
            'barcode' => '899000002',
            'name' => 'Teh Botol Baru',
            'unit_of_measure' => 'botol',
            'purchase_cost' => '2000.00',
            'selling_price' => '2750.00',
            'minimum_stock' => '3.000',
            'tax_rate' => '0.00',
        ], $overrides);
    }
}
