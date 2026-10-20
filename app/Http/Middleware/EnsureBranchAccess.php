<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $branchId = $request->session()->get('active_branch_id');

        if (! $user || ! $branchId) {
            abort(403, 'Pilih cabang terlebih dahulu.');
        }

        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('is_active', true)
            ->first();

        abort_unless($branch && $user->canAccessBranch($branch), 404);

        $request->attributes->set('activeBranch', $branch);

        return $next($request);
    }
}
