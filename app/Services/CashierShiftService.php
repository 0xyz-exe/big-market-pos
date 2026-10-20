<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Sale;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashierShiftService
{
    public function open(Branch $branch, User $cashier, string $openingCash): CashierShift
    {
        $this->authorizeBranch($branch, $cashier, true);
        $openingCash = $this->normalizeCash($openingCash);

        return DB::transaction(function () use ($branch, $cashier, $openingCash): CashierShift {
            $lockedBranch = Branch::query()->whereKey($branch->getKey())->lockForUpdate()->firstOrFail();
            if (! $lockedBranch->is_active) {
                throw new AuthorizationException('Cabang tidak aktif.');
            }
            User::query()->whereKey($cashier->getKey())->lockForUpdate()->firstOrFail();

            $existing = CashierShift::query()
                ->where('branch_id', $branch->getKey())
                ->where('user_id', $cashier->getKey())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                throw new DomainException('Anda sudah memiliki shift kasir yang terbuka di cabang ini.');
            }

            return CashierShift::query()->create([
                'branch_id' => $branch->getKey(),
                'user_id' => $cashier->getKey(),
                'open_user_id' => $cashier->getKey(),
                'status' => 'open',
                'opening_cash' => $openingCash,
                'opened_at' => now($branch->timezone),
            ]);
        }, 3);
    }

    public function close(Branch $branch, User $actor, CashierShift $shift, string $closingCash): CashierShift
    {
        $this->authorizeBranch($branch, $actor, false);
        if ((int) $shift->branch_id !== (int) $branch->getKey()) {
            abort(404);
        }
        if ((int) $shift->user_id !== (int) $actor->getKey() && ! $actor->hasPermission('shifts.close_all')) {
            throw new AuthorizationException('Anda hanya dapat menutup shift milik sendiri.');
        }
        $closingCash = $this->normalizeCash($closingCash);

        return DB::transaction(function () use ($branch, $shift, $closingCash): CashierShift {
            $lockedShift = CashierShift::query()->whereKey($shift->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedShift->status !== 'open') {
                throw new DomainException('Shift kasir ini sudah ditutup.');
            }

            $cashSales = (string) Sale::query()
                ->where('cashier_shift_id', $lockedShift->getKey())
                ->where('status', 'completed')
                ->where('payment_method', 'cash')
                ->sum('total');
            $expectedCash = bcadd((string) $lockedShift->opening_cash, $cashSales, 2);
            $this->assertCashRange($expectedCash);
            $variance = bcsub($closingCash, $expectedCash, 2);

            $lockedShift->forceFill([
                'status' => 'closed',
                'open_user_id' => null,
                'closing_cash' => $closingCash,
                'expected_cash' => $expectedCash,
                'variance' => $variance,
                'closed_at' => now($branch->timezone),
            ])->save();

            return $lockedShift;
        }, 3);
    }

    private function authorizeBranch(Branch $branch, User $user, bool $requireActive): void
    {
        $hasBranchAccess = $user->hasPermission('branches.access_all')
            || $user->branches()->whereKey($branch->getKey())->exists();

        if (! $user->is_active || ! $hasBranchAccess || ! $user->hasPermission('shifts.manage')) {
            throw new AuthorizationException('Anda tidak berwenang mengelola shift di cabang ini.');
        }
        if ($requireActive && ! $branch->is_active) {
            throw new AuthorizationException('Cabang tidak aktif.');
        }
    }

    private function normalizeCash(string $amount): string
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('Nominal kas harus nonnegatif dan memiliki maksimal dua digit desimal.');
        }

        $amount = bcadd($amount, '0', 2);
        $this->assertCashRange($amount);

        return $amount;
    }

    private function assertCashRange(string $amount): void
    {
        if (bccomp($amount, '999999999999.99', 2) > 0) {
            throw new InvalidArgumentException('Nominal kas melampaui batas yang didukung.');
        }
    }
}
