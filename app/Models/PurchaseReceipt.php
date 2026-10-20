<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'branch_id', 'purchase_order_id', 'received_by', 'receipt_number', 'idempotency_key',
    'request_fingerprint', 'received_at',
])]
class PurchaseReceipt extends Model
{
    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Penerimaan barang tidak dapat diubah.'));
        static::deleting(static fn (): never => throw new LogicException('Riwayat penerimaan barang tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
