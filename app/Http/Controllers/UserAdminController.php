<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with(['roles', 'branches'])->orderBy('name')->paginate(25),
            'roles' => Role::query()->orderBy('label')->get(['id', 'name', 'label']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedUser($request);
        $validated['email'] = mb_strtolower(trim($validated['email']));

        DB::transaction(function () use ($validated): void {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill(['must_change_password' => true])->save();
            $user->roles()->sync($validated['role_ids']);
            $user->branches()->sync($this->branchAssignments($validated['branch_ids'], $validated['default_branch_id']));
        });

        return redirect()->route('admin.users.index')->with('status', 'Pengguna dibuat. Pengguna wajib mengganti kata sandi sementara saat masuk.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validatedUser($request, $user);
        $validated['email'] = mb_strtolower(trim($validated['email']));

        DB::transaction(function () use ($validated, $user): void {
            $this->ensureAdministratorContinuity($user, $validated['role_ids'], (bool) $validated['is_active']);
            $user->forceFill([
                'name' => trim($validated['name']),
                'email' => $validated['email'],
                'is_active' => (bool) $validated['is_active'],
            ])->save();
            $user->roles()->sync($validated['role_ids']);
            $user->branches()->sync($this->branchAssignments($validated['branch_ids'], $validated['default_branch_id']));
        });

        return redirect()->route('admin.users.index')->with('status', 'Pengguna diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => true,
        ])->save();

        return redirect()->route('admin.users.index')->with('status', 'Kata sandi sementara diperbarui; pengguna wajib menggantinya saat masuk.');
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        $email = $request->input('email');
        $branchIdsInput = $request->input('branch_ids', []);
        $request->merge([
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
        ]);
        $branchIds = is_array($branchIdsInput) ? array_map('intval', $branchIdsInput) : [];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('is_active', true)],
            'default_branch_id' => ['required', 'integer', Rule::in($branchIds)],
            'is_active' => $user === null ? ['sometimes', 'boolean'] : ['required', 'boolean'],
        ];

        if ($user === null) {
            $rules['password'] = ['required', 'confirmed', Password::min(12)];
        }

        return $request->validate($rules);
    }

    private function ensureAdministratorContinuity(User $user, array $roleIds, bool $isActive): void
    {
        if ($user->is(auth()->user()) && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }

        $willManageUsers = Role::query()
            ->whereKey($roleIds)
            ->whereHas('permissions', fn (Builder $query) => $query->where('name', 'users.manage'))
            ->exists();
        if ($isActive && $willManageUsers) {
            return;
        }

        $activeAdministrators = User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn (Builder $query) => $query->where('name', 'users.manage'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        $otherActiveAdministrators = $activeAdministrators->contains(
            fn (User $administrator) => $administrator->id !== $user->id,
        );

        if (! $otherActiveAdministrators) {
            throw ValidationException::withMessages(['role_ids' => 'Pertahankan setidaknya satu pengguna aktif dengan izin mengelola pengguna.']);
        }
    }

    private function branchAssignments(array $branchIds, int|string $defaultBranchId): array
    {
        return collect($branchIds)->mapWithKeys(fn (int $branchId) => [
            $branchId => ['is_default' => $branchId === (int) $defaultBranchId],
        ])->all();
    }
}
