<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BootstrapBigMarket extends Command
{
    protected $signature = 'app:bootstrap';

    protected $description = 'Create the first Big Market super administrator and branch';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('Perintah bootstrap harus dijalankan di terminal interaktif agar kata sandi tidak dikirim sebagai argumen.');

            return self::FAILURE;
        }

        if (User::query()->exists()) {
            $this->components->error('Bootstrap hanya dapat dijalankan sebelum akun pengguna pertama dibuat.');

            return self::FAILURE;
        }

        $name = trim($this->ask('Nama lengkap administrator') ?? '');
        $email = mb_strtolower(trim($this->ask('Email administrator') ?? ''));
        $branchCode = mb_strtoupper(trim($this->ask('Kode cabang pertama') ?? ''));
        $branchName = trim($this->ask('Nama cabang pertama') ?? '');
        $timezone = trim($this->ask('Zona waktu cabang', 'Asia/Jakarta') ?? 'Asia/Jakarta');
        $password = $this->secret('Kata sandi (minimal 12 karakter)');
        $passwordConfirmation = $this->secret('Ulangi kata sandi');

        if ($name === '' || mb_strlen($name) > 255) {
            $this->components->error('Nama administrator wajib diisi dan maksimal 255 karakter.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::query()->where('email', $email)->exists()) {
            $this->components->error('Email administrator tidak valid atau sudah digunakan.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Z0-9_-]{2,32}$/D', $branchCode) || Branch::query()->where('code', $branchCode)->exists()) {
            $this->components->error('Kode cabang harus unik dan berisi 2–32 huruf, angka, tanda hubung, atau garis bawah.');

            return self::FAILURE;
        }

        if ($branchName === '' || mb_strlen($branchName) > 255 || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $this->components->error('Nama cabang atau zona waktu tidak valid.');

            return self::FAILURE;
        }

        if (! is_string($password) || mb_strlen($password) < 12 || $password !== $passwordConfirmation) {
            $this->components->error('Kata sandi harus memiliki minimal 12 karakter dan kedua masukan harus sama.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $email, $branchCode, $branchName, $timezone, $password): void {
                app(AccessControlSeeder::class)->run();

                $branch = Branch::query()->create([
                    'code' => $branchCode,
                    'name' => $branchName,
                    'timezone' => $timezone,
                    'is_active' => true,
                ]);

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'is_active' => true,
                ]);

                $role = Role::query()->where('name', 'super_admin')->firstOrFail();
                $user->roles()->attach($role);
            }, 3);
        } catch (QueryException) {
            $this->components->error('Bootstrap gagal karena data duplikat atau kendala database. Tidak ada kredensial yang ditampilkan.');

            return self::FAILURE;
        }

        $this->components->info('Administrator dan cabang awal berhasil disiapkan. Kata sandi tidak ditampilkan.');

        return self::SUCCESS;
    }
}
