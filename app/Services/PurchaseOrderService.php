<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class PurchaseOrderService
{
    /**
     * @param  array<int, array{product_id: int|string, quantity_ordered: int|string, unit_cost: int|string}>  $requestedItems
     */
    public function createOrder(Branch $branch, User $actor, int $supplierId, array $requestedItems, ?string $notes = null): PurchaseOrder
    {
        $this->authorize($branch, $actor, 'purchases.manage');
        $items = $this->normalizeOrderItems($requestedItems);
        $supplier = Supplier::query()->whereKey($supplierId)->where('is_active', true)->firstOrFail();
        $productIds = array_column($items, 'product_id');

        return DB::transaction(function () use ($branch, $actor, $supplier, $items, $productIds, $notes): PurchaseOrder {
            $branchProducts = DB::table('branch_products')
                ->where('branch_id', $branch->getKey())
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($branchProducts->count() !== count($items) || $products->count() !== count($items)) {
                throw new InvalidArgumentException('Produk pesanan harus aktif dan terdaftar pada cabang ini.');
            }

            $orderLines = [];
            $subtotal = '0.00';
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $lineTotal = $this->roundMoney(bcmul($item['unit_cost'], $item['quantity'], 5));
                $this->assertMoneyRange($lineTotal);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $orderLines[] = [
                    ...$item,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'line_total' => $lineTotal,
                ];
            }
            $this->assertMoneyRange($subtotal);

            $order = PurchaseOrder::query()->create([
                'branch_id' => $branch->getKey(),
                'supplier_id' => $supplier->getKey(),
                'created_by' => $actor->getKey(),
                'order_number' => 'BM-'.$branch->code.'-PO-'.Str::upper((string) Str::ulid()),
                'status' => 'ordered',
                'subtotal' => $subtotal,
                'ordered_at' => now($branch->timezone),
                'notes' => $notes === null ? null : trim($notes),
            ]);
            foreach ($orderLines as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_sku' => $line['product_sku'],
                    'product_name' => $line['product_name'],
                    'quantity_ordered' => $line['quantity'],
                    'quantity_received' => '0.000',
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order->load(['supplier', 'items']);
        }, 3);
    }

    /**
     * @param  array<int, array{purchase_order_item_id: int|string, quantity: int|string}>  $requestedItems
     *
     * @throws JsonException
     */
    public function receive(Branch $branch, User $actor, PurchaseOrder $order, array $requestedItems, string $idempotencyKey): PurchaseReceipt
    {
        $this->authorize($branch, $actor, 'purchases.receive');
        if ((int) $order->branch_id !== (int) $branch->getKey()) {
            abort(404);
        }
        $items = $this->normalizeReceiptItems($requestedItems);
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $idempotencyKey)) {
            throw new InvalidArgumentException('Kunci idempotensi penerimaan tidak valid.');
        }
        $fingerprint = hash('sha256', json_encode([
            'purchase_order_id' => $order->getKey(),
            'items' => $items,
        ], JSON_THROW_ON_ERROR));

        $existing = $this->existingReceipt($branch, $idempotencyKey);
        if ($existing !== null) {
            return $this->resolveReplay($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($branch, $actor, $order, $items, $idempotencyKey, $fingerprint): PurchaseReceipt {
                $lockedBranch = Branch::query()->whereKey($branch->getKey())->lockForUpdate()->firstOrFail();
                if (! $lockedBranch->is_active) {
                    throw new AuthorizationException('Cabang tidak aktif.');
                }
                $lockedOrder = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedOrder->branch_id !== (int) $branch->getKey()) {
                    abort(404);
                }

                $existing = $this->existingReceipt($branch, $idempotencyKey);
                if ($existing !== null) {
                    return $this->resolveReplay($existing, $fingerprint);
                }
                if (! in_array($lockedOrder->status, ['ordered', 'partially_received'], true)) {
                    throw new InvalidArgumentException('Pesanan ini sudah diterima seluruhnya atau tidak dapat diterima.');
                }

                $requestedIds = array_column($items, 'purchase_order_item_id');
                $orderItems = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $lockedOrder->getKey())
                    ->whereIn('id', $requestedIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                if ($orderItems->count() !== count($items)) {
                    throw new InvalidArgumentException('Setiap rincian penerimaan harus berasal dari pesanan ini.');
                }

                $productIds = $orderItems->pluck('product_id')->map(fn ($id) => (int) $id)->all();
                $branchProducts = DB::table('branch_products')
                    ->where('branch_id', $branch->getKey())
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');
                $balances = InventoryBalance::query()
                    ->where('branch_id', $branch->getKey())
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');
                $products = Product::query()->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($branchProducts->count() !== count($productIds) || $balances->count() !== count($productIds) || $products->count() !== count($productIds)) {
                    throw new InvalidArgumentException('Produk atau saldo stok pesanan tidak lagi tersedia di cabang ini.');
                }

                $receipt = PurchaseReceipt::query()->create([
                    'branch_id' => $branch->getKey(),
                    'purchase_order_id' => $lockedOrder->getKey(),
                    'received_by' => $actor->getKey(),
                    'receipt_number' => 'BM-'.$branch->code.'-GRN-'.Str::upper((string) Str::ulid()),
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'received_at' => now($branch->timezone),
                ]);

                foreach ($items as $item) {
                    $orderItem = $orderItems->get($item['purchase_order_item_id']);
                    $balance = $balances->get($orderItem->product_id);
                    $branchProduct = $branchProducts->get($orderItem->product_id);
                    $product = $products->get($orderItem->product_id);
                    $outstanding = bcsub((string) $orderItem->quantity_ordered, (string) $orderItem->quantity_received, 3);
                    if (bccomp($item['quantity'], $outstanding, 3) > 0) {
                        throw new InvalidArgumentException("Jumlah penerimaan {$orderItem->product_name} melebihi sisa pesanan.");
                    }

                    $quantityAfter = bcadd((string) $balance->quantity_on_hand, $item['quantity'], 3);
                    if (bccomp($quantityAfter, '99999999999.999', 3) > 0) {
                        throw new InvalidArgumentException('Saldo stok setelah penerimaan melampaui batas yang didukung.');
                    }
                    $newReceived = bcadd((string) $orderItem->quantity_received, $item['quantity'], 3);
                    $lineTotal = $this->roundMoney(bcmul((string) $orderItem->unit_cost, $item['quantity'], 5));
                    $this->assertMoneyRange($lineTotal);

                    $receipt->items()->create([
                        'purchase_order_item_id' => $orderItem->getKey(),
                        'product_id' => $product->getKey(),
                        'quantity' => $item['quantity'],
                        'unit_cost' => $orderItem->unit_cost,
                        'line_total' => $lineTotal,
                    ]);
                    $orderItem->forceFill(['quantity_received' => $newReceived])->save();
                    $balance->forceFill(['quantity_on_hand' => $quantityAfter])->save();
                    InventoryMovement::query()->create([
                        'branch_id' => $branch->getKey(),
                        'product_id' => $product->getKey(),
                        'user_id' => $actor->getKey(),
                        'movement_type' => 'purchase_in',
                        'quantity_delta' => $item['quantity'],
                        'quantity_after' => $quantityAfter,
                        'source_type' => 'purchase_receipt',
                        'source_id' => $receipt->getKey(),
                        'reason' => 'Penerimaan '.$receipt->receipt_number,
                    ]);

                    $oldQuantity = bcsub($quantityAfter, $item['quantity'], 3);
                    $oldUnitCost = $branchProduct->purchase_cost === null
                        ? (string) $product->purchase_cost
                        : (string) $branchProduct->purchase_cost;
                    $branchProductsCost = $this->weightedAverageCost(
                        $oldQuantity,
                        $oldUnitCost,
                        $item['quantity'],
                        (string) $orderItem->unit_cost,
                    );
                    DB::table('branch_products')
                        ->where('branch_id', $branch->getKey())
                        ->where('product_id', $product->getKey())
                        ->update(['purchase_cost' => $branchProductsCost, 'updated_at' => now()]);
                    $branchProduct->purchase_cost = $branchProductsCost;
                    $balance->quantity_on_hand = $quantityAfter;
                    $orderItem->quantity_received = $newReceived;
                }

                $stillOutstanding = $lockedOrder->items()
                    ->whereColumn('quantity_received', '<', 'quantity_ordered')
                    ->exists();
                $lockedOrder->forceFill([
                    'status' => $stillOutstanding ? 'partially_received' : 'received',
                ])->save();

                return $receipt->load('items');
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existingReceipt($branch, $idempotencyKey);
            if ($existing !== null) {
                return $this->resolveReplay($existing, $fingerprint);
            }

            throw $exception;
        }
    }

    private function normalizeOrderItems(array $requestedItems): array
    {
        if ($requestedItems === [] || count($requestedItems) > 100) {
            throw new InvalidArgumentException('Pesanan harus memuat 1 sampai 100 produk.');
        }
        $items = [];
        foreach ($requestedItems as $item) {
            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            if (! is_int($productId) || $productId < 1 || isset($items[$productId])) {
                throw new InvalidArgumentException('Produk pesanan tidak valid atau terduplikasi.');
            }
            $items[$productId] = [
                'product_id' => $productId,
                'quantity' => $this->normalizeQuantity($item['quantity_ordered'] ?? null, 'Jumlah pesanan'),
                'unit_cost' => $this->normalizeMoney($item['unit_cost'] ?? null, 'Harga beli'),
            ];
        }
        ksort($items, SORT_NUMERIC);

        return array_values($items);
    }

    private function normalizeReceiptItems(array $requestedItems): array
    {
        if ($requestedItems === [] || count($requestedItems) > 100) {
            throw new InvalidArgumentException('Penerimaan harus memuat 1 sampai 100 rincian pesanan.');
        }
        $items = [];
        foreach ($requestedItems as $item) {
            $itemId = filter_var($item['purchase_order_item_id'] ?? null, FILTER_VALIDATE_INT);
            if (! is_int($itemId) || $itemId < 1 || isset($items[$itemId])) {
                throw new InvalidArgumentException('Rincian pesanan penerimaan tidak valid atau terduplikasi.');
            }
            $items[$itemId] = [
                'purchase_order_item_id' => $itemId,
                'quantity' => $this->normalizeQuantity($item['quantity'] ?? null, 'Jumlah diterima'),
            ];
        }
        ksort($items, SORT_NUMERIC);

        return array_values($items);
    }

    private function normalizeQuantity(mixed $quantity, string $label): string
    {
        if (! is_string($quantity) && ! is_int($quantity) || ! preg_match('/^\d{1,11}(?:\.\d{1,3})?$/D', (string) $quantity)) {
            throw new InvalidArgumentException("{$label} harus memiliki maksimal tiga digit desimal.");
        }
        $quantity = bcadd((string) $quantity, '0', 3);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException("{$label} harus lebih dari nol.");
        }

        return $quantity;
    }

    private function normalizeMoney(mixed $amount, string $label): string
    {
        if (! is_string($amount) && ! is_int($amount) || ! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', (string) $amount)) {
            throw new InvalidArgumentException("{$label} harus berupa nominal positif dengan maksimal dua digit desimal.");
        }
        $amount = bcadd((string) $amount, '0', 2);
        $this->assertMoneyRange($amount);

        return $amount;
    }

    private function weightedAverageCost(string $oldQuantity, string $oldCost, string $receivedQuantity, string $receivedCost): string
    {
        $totalQuantity = bcadd($oldQuantity, $receivedQuantity, 3);
        $totalCost = bcadd(bcmul($oldQuantity, $oldCost, 5), bcmul($receivedQuantity, $receivedCost, 5), 5);
        $average = bcdiv($totalCost, $totalQuantity, 5);

        return $this->roundMoney($average);
    }

    private function roundMoney(string $amount): string
    {
        $amount = bcadd($amount, '0', 5);
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad($fraction, 5, '0');
        $rounded = $whole.'.'.substr($fraction, 0, 2);

        return (int) $fraction[2] >= 5 ? bcadd($rounded, '0.01', 2) : $rounded;
    }

    private function assertMoneyRange(string $amount): void
    {
        if (bccomp($amount, '999999999999.99', 2) > 0) {
            throw new InvalidArgumentException('Nilai pembelian melampaui batas nominal yang didukung.');
        }
    }

    private function authorize(Branch $branch, User $actor, string $permission): void
    {
        if (! $branch->is_active || ! $actor->canAccessBranch($branch) || ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('Anda tidak berwenang mengelola pengadaan di cabang ini.');
        }
    }

    private function existingReceipt(Branch $branch, string $idempotencyKey): ?PurchaseReceipt
    {
        return PurchaseReceipt::query()
            ->where('branch_id', $branch->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function resolveReplay(PurchaseReceipt $receipt, string $fingerprint): PurchaseReceipt
    {
        if (! hash_equals($receipt->request_fingerprint, $fingerprint)) {
            throw new IdempotencyConflictException;
        }

        return $receipt->load('items');
    }
}
