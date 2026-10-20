<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'branch_id', 'supplier_id', 'created_by', 'order_number', 'status', 'subtotal', 'ordered_at', 'notes',
])]
class PurchaseOrder extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            if (array_diff(array_keys($order->getDirty()), ['status', 'updated_at']) !== []) {
                throw new LogicException('Data pesanan pembelian tidak dapat diubah setelah dibuat.');
            }
        });
        static::deleting(static fn (): never => throw new LogicException('Riwayat pesanan pembelian tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'ordered_at' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }
}
