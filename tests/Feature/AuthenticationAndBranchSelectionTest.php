<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationAndBranchSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_in_indonesian(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Selamat datang kembali')
            ->assertSee('Masuk ke sistem');
    }

    public function test_active_staff_can_sign_in_and_receive_a_regenerated_session(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-1']);
        $sessionId = session()->getId();

        $response = $this->post(route('login.store'), [
            'email' => strtoupper($user->email),
            'password' => 'Correct-Horse-1',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('branches.select'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_credentials_and_inactive_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-1']);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $user->forceFill(['is_active' => false])->save();
        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'Correct-Horse-1'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_sees_only_assigned_branches_and_cannot_select_another_branch(): void
    {
        $user = User::factory()->create();
        $assigned = Branch::create(['code' => 'JKT-02', 'name' => 'Jakarta Selatan']);
        $unassigned = Branch::create(['code' => 'BDG-02', 'name' => 'Bandung Timur']);
        $user->branches()->attach($assigned);

        $this->actingAs($user)
            ->get(route('branches.select'))
            ->assertOk()
            ->assertSee('Jakarta Selatan')
            ->assertDontSee('Bandung Timur');

        $this->actingAs($user)
            ->post(route('branches.select.store'), ['branch_id' => $unassigned->id])
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('branches.select.store'), ['branch_id' => $assigned->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_branch_id', $assigned->id);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan cabang')
            ->assertSee('Jakarta Selatan');
    }

    public function test_deactivated_staff_are_logged_out_on_the_next_authenticated_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get(route('branches.select'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_authenticated_staff_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
