<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_and_role_matrix_seed_idempotently_without_creating_a_default_login(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(7, Role::query()->count());
        $this->assertSame(22, Permission::query()->count());
        $this->assertSame(6, ExpenseCategory::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertTrue(Role::query()->where('name', 'owner')->firstOrFail()->permissions()->where('name', 'branches.access_all')->exists());
        $this->assertFalse(Role::query()->where('name', 'cashier')->firstOrFail()->permissions()->where('name', 'branches.access_all')->exists());
    }
}
