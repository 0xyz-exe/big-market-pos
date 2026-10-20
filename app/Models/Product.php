<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id', 'sku', 'barcode', 'name', 'description', 'unit_of_measure',
    'purchase_cost', 'default_selling_price', 'tax_rate', 'is_active',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
            'default_selling_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function setSkuAttribute(string $value): void
    {
        $this->attributes['sku'] = mb_strtoupper(trim($value));
    }

    public function setBarcodeAttribute(?string $value): void
    {
        $this->attributes['barcode'] = $value === null ? null : trim($value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_products')
            ->withPivot(['selling_price', 'purchase_cost', 'minimum_stock', 'is_available'])
            ->withTimestamps();
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
