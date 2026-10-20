<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'purchase_order_id', 'product_id', 'product_sku', 'product_name', 'quantity_ordered',
    'quantity_received', 'unit_cost', 'line_total',
])]
class PurchaseOrderItem extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if (array_diff(array_keys($item->getDirty()), ['quantity_received', 'updated_at']) !== []) {
                throw new LogicException('Rincian pesanan pembelian tidak dapat diubah setelah dibuat.');
            }
        });
        static::deleting(static fn (): never => throw new LogicException('Riwayat pesanan pembelian tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
