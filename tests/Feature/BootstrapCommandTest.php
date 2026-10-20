<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_refuses_non_interactive_password_input(): void
    {
        $this->artisan('app:bootstrap --no-interaction')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_interactive_bootstrap_provisions_the_first_admin_and_branch(): void
    {
        $this->artisan('app:bootstrap')
            ->expectsQuestion('Nama lengkap administrator', 'Admin Pengujian')
            ->expectsQuestion('Email administrator', 'admin-test@example.invalid')
            ->expectsQuestion('Kode cabang pertama', 'JKT-TEST')
            ->expectsQuestion('Nama cabang pertama', 'Cabang Pengujian')
            ->expectsQuestion('Zona waktu cabang', 'Asia/Jakarta')
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'test-only-not-a-real-secret-1')
            ->expectsQuestion('Ulangi kata sandi', 'test-only-not-a-real-secret-1')
            ->expectsOutputToContain('Administrator dan cabang awal berhasil disiapkan.')
            ->assertExitCode(0);

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('branches', ['code' => 'JKT-TEST', 'name' => 'Cabang Pengujian']);
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
        $this->assertTrue(Hash::check(
            'test-only-not-a-real-secret-1',
            User::query()->firstOrFail()->password,
        ));
    }
}
