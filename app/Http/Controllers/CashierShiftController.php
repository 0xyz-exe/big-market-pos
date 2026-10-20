<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Services\CashierShiftService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class CashierShiftController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $request->attributes->get('activeBranch');
        $user = $request->user();
        $canCloseOthers = $user->hasPermission('shifts.close_all');
        $shifts = CashierShift::query()
            ->with('cashier:id,name')
            ->where('branch_id', $branch->getKey())
            ->when(! $canCloseOthers, fn ($query) => $query->where('user_id', $user->getKey()))
            ->orderByDesc('opened_at')
            ->paginate(20);

        return view('shifts.index', [
            'branch' => $branch,
            'openShift' => CashierShift::query()
                ->where('branch_id', $branch->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', 'open')
                ->first(),
            'shifts' => $shifts,
            'canCloseOthers' => $canCloseOthers,
        ]);
    }

    public function open(Request $request, CashierShiftService $service): RedirectResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
        ]);

        try {
            $service->open($request->attributes->get('activeBranch'), $request->user(), $validated['opening_cash']);
        } catch (DomainException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['opening_cash' => $exception->getMessage()]);
        }

        return redirect()->route('shifts.index')->with('status', 'Shift kasir dibuka. Penjualan tunai akan tercatat pada shift ini.');
    }

    public function close(Request $request, CashierShift $shift, CashierShiftService $service): RedirectResponse
    {
        $validated = $request->validate([
            'closing_cash' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
        ]);

        try {
            $service->close($request->attributes->get('activeBranch'), $request->user(), $shift, $validated['closing_cash']);
        } catch (DomainException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['closing_cash' => $exception->getMessage()]);
        }

        return redirect()->route('shifts.index')->with('status', 'Shift ditutup. Selisih kas tercatat untuk rekonsiliasi.');
    }
}
