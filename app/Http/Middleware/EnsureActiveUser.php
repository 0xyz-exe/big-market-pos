<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if ($user?->must_change_password && ! $request->routeIs(
            'account.password.edit',
            'account.password.update',
            'logout',
        )) {
            return redirect()->route('account.password.edit')
                ->with('status', 'Silakan ganti kata sandi sementara sebelum melanjutkan.');
        }

        return $next($request);
    }
}
