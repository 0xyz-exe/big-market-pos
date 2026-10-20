<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $request->attributes->get('activeBranch');
        $search = trim((string) ($request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ])['q'] ?? ''));

        $sales = Sale::query()
            ->where('branch_id', $branch->getKey())
            ->with('cashier:id,name')
            ->when($search !== '', fn ($query) => $query->whereRaw(
                'LOWER(transaction_number) LIKE ?',
                ['%'.mb_strtolower($search).'%'],
            ))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', compact('branch', 'sales', 'search'));
    }

    public function receipt(Request $request, Sale $sale): View
    {
        $branch = $request->attributes->get('activeBranch');
        abort_unless($branch !== null && (int) $sale->branch_id === (int) $branch->getKey(), 404);

        return view('sales.receipt', [
            'sale' => $sale->load(['branch', 'cashier', 'items']),
        ]);
    }
}
