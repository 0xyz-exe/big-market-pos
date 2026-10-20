<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id', 'product_id', 'user_id', 'movement_type', 'quantity_delta',
    'quantity_after', 'source_type', 'source_id', 'reason',
])]
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
