<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchSelectionController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->when(
                ! $request->user()->hasPermission('branches.access_all'),
                fn ($query) => $query->whereHas(
                    'users',
                    fn ($users) => $users->where('users.id', $request->user()->getKey()),
                ),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'address']);

        return view('branches.select', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branch = Branch::query()->where('is_active', true)->findOrFail($validated['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch), 404);

        $request->session()->put('active_branch_id', $branch->getKey());

        return redirect()->route('dashboard');
    }
}
