<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->attributes->get('activeBranch');
        $user = $this->user();

        return $branch instanceof Branch
            && $user !== null
            && $user->canAccessBranch($branch)
            && $user->hasPermission('sales.create');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'string', 'regex:/^\\d{1,11}(?:\\.\\d{1,3})?$/D'],
            'amount_received' => ['required', 'string', 'regex:/^\\d{1,12}(?:\\.\\d{1,2})?$/D'],
            'discount_percent' => ['sometimes', 'string', 'regex:/^\\d{1,3}(?:\\.\\d{1,2})?$/D'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
