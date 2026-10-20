<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Expense extends Model
{
    protected $fillable = [
        'branch_id', 'expense_category_id', 'created_by', 'decided_by', 'expense_date',
        'description', 'amount', 'payment_method', 'receipt_reference', 'status',
        'decision_note', 'decided_at', 'receipt_path', 'receipt_original_name',
        'receipt_mime_type', 'receipt_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'decided_at' => 'immutable_datetime',
            'receipt_size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Expense $expense): void {
            if ($expense->getOriginal('status') !== 'pending') {
                throw new LogicException('A decided expense cannot be changed.');
            }
        });

        static::deleting(function (Expense $expense): void {
            if ($expense->status !== 'pending') {
                throw new LogicException('A decided expense cannot be deleted.');
            }
        });
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
