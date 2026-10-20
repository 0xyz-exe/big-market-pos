<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'purchase_receipt_id', 'purchase_order_item_id', 'product_id', 'quantity', 'unit_cost', 'line_total',
])]
class PurchaseReceiptItem extends Model
{
    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Rincian penerimaan barang tidak dapat diubah.'));
        static::deleting(static fn (): never => throw new LogicException('Riwayat penerimaan barang tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
