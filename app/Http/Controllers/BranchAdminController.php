<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashierShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchAdminController extends Controller
{
    public function index(Request $request): View
    {
        $branches = $request->user()->hasPermission('branches.access_all')
            ? Branch::query()
            : $request->user()->branches()->getQuery();

        return view('admin.branches.index', [
            'branches' => $branches->orderBy('name')->paginate(25),
            'canCreate' => $request->user()->hasPermission('branches.access_all'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('branches.access_all'), 403);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('branches', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
        ]);
        Branch::query()->create([...$validated, 'is_active' => true]);

        return redirect()->route('admin.branches.index')->with('status', 'Cabang ditambahkan.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->assertManageable($request, $branch);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            $activeBranches = Branch::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if (! (bool) $validated['is_active'] && $activeBranches->count() <= 1) {
                throw ValidationException::withMessages(['is_active' => 'Setidaknya satu cabang harus tetap aktif.']);
            }
            if (! (bool) $validated['is_active'] && CashierShift::query()
                ->where('branch_id', $branch->getKey())
                ->where('status', 'open')
                ->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Tutup semua shift kasir sebelum menonaktifkan cabang.']);
            }

            $branch->forceFill([
                'name' => trim($validated['name']),
                'address' => $validated['address'] === null ? null : trim($validated['address']),
                'timezone' => $validated['timezone'],
                'is_active' => (bool) $validated['is_active'],
            ])->save();
        });

        return redirect()->route('admin.branches.index')->with('status', 'Cabang diperbarui.');
    }

    private function assertManageable(Request $request, Branch $branch): void
    {
        $canManage = $request->user()->hasPermission('branches.access_all')
            || $request->user()->branches()->whereKey($branch->getKey())->exists();
        abort_unless($canManage, 404);
    }
}
