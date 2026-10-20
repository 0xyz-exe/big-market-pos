<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'branch_id', 'cashier_id', 'cashier_shift_id', 'transaction_number', 'idempotency_key', 'request_fingerprint',
    'status', 'payment_method', 'subtotal', 'discount_percent', 'discount_amount', 'discount_reason',
    'tax_total', 'total', 'amount_received', 'change_due', 'completed_at',
])]
class Sale extends Model
{
    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Penjualan selesai tidak dapat diubah.'));
        static::deleting(static fn (): never => throw new LogicException('Penjualan selesai tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'change_due' => 'decimal:2',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
