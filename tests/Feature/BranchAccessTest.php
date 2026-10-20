<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'branch.access'])->get('/_test/branch-context', function (Request $request) {
            return response()->json(['branch_id' => $request->attributes->get('activeBranch')->id]);
        });
    }

    public function test_user_can_access_only_an_assigned_active_branch_selected_in_session(): void
    {
        $user = User::factory()->create();
        $assignedBranch = Branch::create(['code' => 'JKT-01', 'name' => 'Jakarta Pusat']);
        $otherBranch = Branch::create(['code' => 'BDG-01', 'name' => 'Bandung']);
        $user->branches()->attach($assignedBranch);
        $this->assertTrue($user->is_active);
        $this->assertTrue($assignedBranch->is_active);
        $this->assertSame(1, $user->branches()->count());
        $this->assertTrue($user->canAccessBranch($assignedBranch));

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $assignedBranch->id])
            ->getJson('/_test/branch-context')
            ->assertOk()
            ->assertJson(['branch_id' => $assignedBranch->id]);

        $this->withSession(['active_branch_id' => $otherBranch->id])
            ->getJson('/_test/branch-context')
            ->assertNotFound();
    }

    public function test_cross_branch_permission_allows_access_to_any_active_branch(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'SBY-01', 'name' => 'Surabaya']);
        $role = Role::create(['name' => 'owner', 'label' => 'Pemilik']);
        $permission = Permission::create(['name' => 'branches.access_all', 'label' => 'Akses semua cabang']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->getJson('/_test/branch-context')
            ->assertOk()
            ->assertJson(['branch_id' => $branch->id]);
    }

    public function test_inactive_users_and_branches_are_denied(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['code' => 'DPS-01', 'name' => 'Denpasar', 'is_active' => false]);
        $user->branches()->attach($branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->getJson('/_test/branch-context')
            ->assertNotFound();

        $activeBranch = Branch::create(['code' => 'MLG-01', 'name' => 'Malang']);
        $inactiveUser = User::factory()->create(['is_active' => false]);
        $inactiveUser->branches()->attach($activeBranch);

        $this->actingAs($inactiveUser)
            ->withSession(['active_branch_id' => $activeBranch->id])
            ->getJson('/_test/branch-context')
            ->assertNotFound();
    }

    public function test_missing_branch_selection_is_denied(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/_test/branch-context')
            ->assertForbidden();
    }
}
