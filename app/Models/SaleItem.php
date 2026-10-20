<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'sale_id', 'product_id', 'product_sku', 'product_name', 'quantity', 'unit_price', 'subtotal', 'discount_amount',
    'tax_rate', 'tax_amount', 'total', 'unit_cost', 'cost_total',
])]
class SaleItem extends Model
{
    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Rincian penjualan tidak dapat diubah.'));
        static::deleting(static fn (): never => throw new LogicException('Rincian penjualan tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'cost_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
