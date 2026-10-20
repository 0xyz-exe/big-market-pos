<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'branch_id', 'user_id', 'open_user_id', 'status', 'opening_cash', 'closing_cash',
    'expected_cash', 'variance', 'opened_at', 'closed_at',
])]
class CashierShift extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $shift): void {
            if ($shift->getOriginal('status') === 'closed') {
                throw new LogicException('Shift kasir yang sudah ditutup tidak dapat diubah.');
            }
        });
        static::deleting(static fn (): never => throw new LogicException('Riwayat shift kasir tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cashier_shift_id');
    }
}
