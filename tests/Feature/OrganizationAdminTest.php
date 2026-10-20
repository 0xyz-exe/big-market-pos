<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_can_create_a_branch(): void
    {
        $admin = $this->adminWithPermissions(['branches.manage', 'branches.access_all']);

        $this->actingAs($admin)
            ->post(route('admin.branches.store'), [
                'code' => 'bdg-south',
                'name' => 'Bandung Selatan',
                'address' => 'Jalan Cendana 10',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseHas('branches', [
            'code' => 'BDG-SOUTH',
            'name' => 'Bandung Selatan',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_user_with_role_and_branch_and_force_password_change(): void
    {
        $admin = $this->adminWithPermissions(['users.manage']);
        $branch = Branch::query()->create(['code' => 'USR-BR', 'name' => 'Cabang User']);
        $role = Role::query()->create(['name' => 'cashier', 'label' => 'Kasir']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Kasir Baru',
                'email' => 'CASHIER@example.test',
                'password' => 'Initial-Password-123!',
                'password_confirmation' => 'Initial-Password-123!',
                'role_ids' => [$role->id],
                'branch_ids' => [$branch->id],
                'default_branch_id' => $branch->id,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'cashier@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Initial-Password-123!', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->roles()->whereKey($role->id)->exists());
        $this->assertTrue($user->branches()->whereKey($branch->id)->wherePivot('is_default', true)->exists());
    }

    public function test_temporary_password_forces_change_before_accessing_application_pages(): void
    {
        $branch = Branch::query()->create(['code' => 'PWD-BR', 'name' => 'Cabang Password']);
        $user = User::factory()->create([
            'password' => 'Temporary-Password-123!',
            'must_change_password' => true,
        ]);
        $user->branches()->attach($branch, ['is_default' => true]);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('account.password.edit'));

        $this->actingAs($user)
            ->get(route('account.password.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'Temporary-Password-123!',
                'password' => 'Permanent-Password-456!',
                'password_confirmation' => 'Permanent-Password-456!',
            ])
            ->assertRedirect(route('branches.select'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Permanent-Password-456!', $user->fresh()->password));

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_branch_manager_cannot_create_global_branch_or_view_global_user_administration(): void
    {
        $branch = Branch::query()->create(['code' => 'MGR-BR', 'name' => 'Cabang Manajer']);
        $user = User::factory()->create();
        $user->branches()->attach($branch);
        $role = Role::query()->create(['name' => 'branch_manager', 'label' => 'Manajer Cabang']);
        $permissions = collect(['branches.manage'])->map(fn (string $name) => Permission::query()->create([
            'name' => $name,
            'label' => $name,
        ]));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->post(route('admin.branches.store'), [
                'code' => 'UNAUTH',
                'name' => 'Tidak Boleh',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    private function adminWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'admin-'.strtolower(Str::random(8)), 'label' => 'Admin']);
        $permissionModels = collect($permissions)->map(fn (string $name) => Permission::query()->firstOrCreate(
            ['name' => $name],
            ['label' => $name],
        ));
        $role->permissions()->attach($permissionModels);
        $user->roles()->attach($role);

        return $user;
    }
}
