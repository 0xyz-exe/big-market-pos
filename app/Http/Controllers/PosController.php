<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashierShift;
use App\Models\Product;
use App\Services\SaleCheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        return view('pos.index', [
            'branch' => $request->attributes->get('activeBranch'),
            'openShift' => CashierShift::query()
                ->where('branch_id', $request->attributes->get('activeBranch')->getKey())
                ->where('user_id', $request->user()->getKey())
                ->where('status', 'open')
                ->first(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:120']]);
        $branch = $request->attributes->get('activeBranch');
        $branchId = $branch->getKey();
        $search = trim($validated['q']);

        $products = Product::query()
            ->join('branch_products', function (JoinClause $join) use ($branchId): void {
                $join->on('branch_products.product_id', '=', 'products.id')
                    ->where('branch_products.branch_id', '=', $branchId)
                    ->where('branch_products.is_available', '=', true);
            })
            ->join('inventory_balances', function (JoinClause $join) use ($branchId): void {
                $join->on('inventory_balances.product_id', '=', 'products.id')
                    ->where('inventory_balances.branch_id', '=', $branchId);
            })
            ->where('products.is_active', true)
            ->where(function (Builder $query) use ($search): void {
                $pattern = '%'.mb_strtolower($search).'%';
                $query->whereRaw('LOWER(products.name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(products.sku) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(products.barcode) LIKE ?', [$pattern]);
            })
            ->select([
                'products.id', 'products.sku', 'products.barcode', 'products.name',
                'products.unit_of_measure', 'products.tax_rate',
            ])
            ->selectRaw('CAST(branch_products.selling_price AS VARCHAR) AS selling_price')
            ->selectRaw('CAST(inventory_balances.quantity_on_hand AS VARCHAR) AS stock')
            ->orderBy('products.name')
            ->limit(20)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'unit_of_measure' => $product->unit_of_measure,
                'tax_rate' => $product->tax_rate,
                'selling_price' => (string) $product->getAttribute('selling_price'),
                'stock' => (string) $product->getAttribute('stock'),
            ]);

        return response()->json($products);
    }

    public function store(StoreSaleRequest $request, SaleCheckoutService $checkout): RedirectResponse
    {
        $branch = $request->attributes->get('activeBranch');
        $validated = $request->validated();

        try {
            $sale = $checkout->checkout(
                $branch,
                $request->user(),
                $validated['items'],
                $validated['amount_received'],
                $validated['idempotency_key'],
                $validated['discount_percent'] ?? '0.00',
                $validated['discount_reason'] ?? null,
            );
        } catch (InsufficientStockException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        } catch (IdempotencyConflictException $exception) {
            abort(409, $exception->getMessage());
        }

        return redirect()->route('sales.receipt', $sale);
    }
}
