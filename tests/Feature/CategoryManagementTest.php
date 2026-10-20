<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_categories_can_be_created_renamed_and_deactivated(): void
    {
        [$branch, $user] = $this->categoryUser('products.manage');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('categories.store'), ['name' => 'Minuman Segar', 'description' => 'Kategori minuman'])
            ->assertRedirect(route('categories.index'));

        $category = Category::query()->sole();
        $this->assertSame('minuman-segar', $category->slug);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->put(route('categories.update', $category), [
                'name' => 'Minuman Dingin',
                'description' => 'Kategori diperbarui',
                'is_active' => '0',
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Minuman Dingin',
            'slug' => 'minuman-dingin',
            'is_active' => false,
        ]);
    }

    public function test_expense_categories_can_be_created_and_deactivated_without_deleting_history(): void
    {
        [$branch, $user] = $this->categoryUser('expenses.manage');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->post(route('expense-categories.store'), ['name' => 'Kebersihan'])
            ->assertRedirect(route('expense-categories.index'));

        $category = ExpenseCategory::query()->where('name', 'Kebersihan')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->put(route('expense-categories.update', $category), ['name' => 'Kebersihan', 'is_active' => '0'])
            ->assertRedirect(route('expense-categories.index'));

        $this->assertDatabaseHas('expense_categories', ['id' => $category->id, 'is_active' => false]);
    }

    public function test_category_administration_requires_the_domain_permission(): void
    {
        [$branch, $user] = $this->categoryUser('reports.sales');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('categories.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('expense-categories.index'))
            ->assertForbidden();
    }

    private function categoryUser(string $permissionName): array
    {
        $branch = Branch::query()->create(['code' => 'CAT-'.strtoupper(Str::random(5)), 'name' => 'Cabang Kategori']);
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'cat-'.strtolower(Str::random(8)), 'label' => 'Pengelola kategori']);
        $permission = Permission::query()->create(['name' => $permissionName, 'label' => $permissionName]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $user];
    }
}
