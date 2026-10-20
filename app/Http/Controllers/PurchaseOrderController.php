<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $request->attributes->get('activeBranch');

        return view('purchases.index', [
            'branch' => $branch,
            'orders' => PurchaseOrder::query()
                ->with(['supplier:id,name,code', 'creator:id,name'])
                ->where('branch_id', $branch->getKey())
                ->orderByDesc('ordered_at')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $branch = $request->attributes->get('activeBranch');
        $products = Product::query()
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->where('branch_products.branch_id', $branch->getKey())
            ->where('products.is_active', true)
            ->orderBy('products.name')
            ->get([
                'products.id', 'products.sku', 'products.name',
                'branch_products.purchase_cost', 'branch_products.selling_price',
                'products.purchase_cost as catalog_purchase_cost',
            ]);

        return view('purchases.create', [
            'branch' => $branch,
            'products' => $products,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity_ordered' => ['required', 'string', 'regex:/^\d{1,11}(?:\.\d{1,3})?$/D'],
            'items.*.unit_cost' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
        ]);

        try {
            $order = $service->createOrder(
                $request->attributes->get('activeBranch'),
                $request->user(),
                (int) $validated['supplier_id'],
                $validated['items'],
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return redirect()->route('purchases.show', $order)->with('status', 'Pesanan pembelian dicatat. Pesanan belum dikirim otomatis kepada pemasok.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $branch = $request->attributes->get('activeBranch');
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->getKey(), 404);

        return view('purchases.show', [
            'branch' => $branch,
            'order' => $purchaseOrder->load([
                'supplier', 'creator:id,name', 'items',
                'receipts' => fn ($query) => $query->with(['receiver:id,name', 'items'])->orderByDesc('received_at'),
            ]),
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $branch = $request->attributes->get('activeBranch');
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->getKey(), 404);
        $validated = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,11}(?:\.\d{1,3})?$/D'],
        ]);

        foreach ($validated['items'] as $item) {
            if (! PurchaseOrderItem::query()
                ->whereKey($item['purchase_order_item_id'])
                ->where('purchase_order_id', $purchaseOrder->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['items' => 'Rincian penerimaan harus berasal dari pesanan ini.']);
            }
        }

        try {
            $service->receive(
                $branch,
                $request->user(),
                $purchaseOrder,
                $validated['items'],
                $validated['idempotency_key'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        } catch (IdempotencyConflictException $exception) {
            abort(409, $exception->getMessage());
        }

        return redirect()->route('purchases.show', $purchaseOrder)->with('status', 'Penerimaan barang dicatat dan stok cabang diperbarui.');
    }
}
