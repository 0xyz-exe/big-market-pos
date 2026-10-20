<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class SaleCheckoutService
{
    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $requestedItems
     *
     * @throws JsonException
     */
    public function checkout(
        Branch $branch,
        User $cashier,
        array $requestedItems,
        string $amountReceived,
        string $idempotencyKey,
        string $discountPercent = '0.00',
        ?string $discountReason = null,
    ): Sale {
        if (! $cashier->canAccessBranch($branch) || ! $cashier->hasPermission('sales.create')) {
            throw new AuthorizationException('Anda tidak berwenang memproses penjualan di cabang ini.');
        }

        if (! $branch->is_active) {
            throw new AuthorizationException('Cabang tidak aktif.');
        }

        $items = $this->normalizeItems($requestedItems);
        $amountReceived = $this->normalizeMoney($amountReceived, 'Uang diterima');
        $discountPercent = $this->normalizeDiscountPercent($discountPercent);
        $discountReason = $discountReason === null ? null : trim($discountReason);
        if (bccomp($discountPercent, '0.00', 2) > 0) {
            if (! $cashier->hasPermission('sales.discount')) {
                throw new AuthorizationException('Anda tidak berwenang menerapkan diskon.');
            }
            if ($discountReason === null || $discountReason === '' || mb_strlen($discountReason) > 255) {
                throw new InvalidArgumentException('Alasan diskon wajib diisi maksimal 255 karakter.');
            }
        } else {
            $discountReason = null;
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $idempotencyKey)) {
            throw new InvalidArgumentException('Kunci idempotensi tidak valid.');
        }

        $fingerprint = hash('sha256', json_encode([
            'items' => $items,
            'amount_received' => $amountReceived,
            'discount_percent' => $discountPercent,
            'discount_reason' => $discountReason,
        ], JSON_THROW_ON_ERROR));

        $existing = $this->existingSale($branch, $cashier, $idempotencyKey);
        if ($existing !== null) {
            return $this->resolveReplay($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($branch, $cashier, $items, $amountReceived, $idempotencyKey, $fingerprint, $discountPercent, $discountReason): Sale {
                $existing = $this->existingSale($branch, $cashier, $idempotencyKey);
                if ($existing !== null) {
                    return $this->resolveReplay($existing, $fingerprint);
                }

                $shift = CashierShift::query()
                    ->where('branch_id', $branch->getKey())
                    ->where('user_id', $cashier->getKey())
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
                if ($shift === null) {
                    throw new InvalidArgumentException('Buka shift kasir sebelum memproses penjualan.');
                }

                $existing = $this->existingSale($branch, $cashier, $idempotencyKey);
                if ($existing !== null) {
                    return $this->resolveReplay($existing, $fingerprint);
                }

                $productIds = array_column($items, 'product_id');
                $balances = InventoryBalance::query()
                    ->where('branch_id', $branch->getKey())
                    ->whereIn('product_id', $productIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $existing = $this->existingSale($branch, $cashier, $idempotencyKey);
                if ($existing !== null) {
                    return $this->resolveReplay($existing, $fingerprint);
                }

                $branchProducts = DB::table('branch_products')
                    ->where('branch_id', $branch->getKey())
                    ->whereIn('product_id', $productIds)
                    ->where('is_available', true)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

                if ($balances->count() !== count($items) || $branchProducts->count() !== count($items) || $products->count() !== count($items)) {
                    throw new InvalidArgumentException('Satu atau beberapa produk tidak tersedia di cabang aktif.');
                }

                $saleLines = [];
                $subtotal = '0.00';
                $discountTotal = '0.00';
                $taxTotal = '0.00';

                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    $balance = $balances->get($productId);
                    $branchProduct = $branchProducts->get($productId);
                    $product = $products->get($productId);

                    if (bccomp((string) $balance->quantity_on_hand, $item['quantity'], 3) < 0) {
                        throw new InsufficientStockException("Stok {$product->name} tidak mencukupi.");
                    }

                    $unitPrice = (string) $branchProduct->selling_price;
                    $unitCost = $branchProduct->purchase_cost === null
                        ? (string) $product->purchase_cost
                        : (string) $branchProduct->purchase_cost;
                    $lineSubtotal = $this->roundMoney(bcmul($unitPrice, $item['quantity'], 5));
                    $lineDiscount = $this->roundMoney(bcdiv(bcmul($lineSubtotal, $discountPercent, 5), '100', 5));
                    $taxableSubtotal = bcsub($lineSubtotal, $lineDiscount, 2);
                    $taxRate = (string) $product->tax_rate;
                    $lineTax = $this->roundMoney(bcdiv(bcmul($taxableSubtotal, $taxRate, 5), '100', 5));
                    $lineTotal = bcadd($taxableSubtotal, $lineTax, 2);
                    $costTotal = $this->roundMoney(bcmul($unitCost, $item['quantity'], 5));
                    $this->assertMoneyRange($costTotal);

                    $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                    $discountTotal = bcadd($discountTotal, $lineDiscount, 2);
                    $taxTotal = bcadd($taxTotal, $lineTax, 2);
                    $saleLines[] = [
                        'product' => $product,
                        'balance' => $balance,
                        'quantity' => $item['quantity'],
                        'unit_price' => $unitPrice,
                        'subtotal' => $lineSubtotal,
                        'discount_amount' => $lineDiscount,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $lineTax,
                        'total' => $lineTotal,
                        'unit_cost' => $unitCost,
                        'cost_total' => $costTotal,
                    ];
                }

                $this->assertMoneyRange($subtotal);
                $this->assertMoneyRange($discountTotal);
                $this->assertMoneyRange($taxTotal);
                $total = bcadd(bcsub($subtotal, $discountTotal, 2), $taxTotal, 2);
                $this->assertMoneyRange($total);

                if (bccomp($amountReceived, $total, 2) < 0) {
                    throw new InvalidArgumentException('Uang diterima tidak mencukupi total penjualan.');
                }

                $changeDue = bcsub($amountReceived, $total, 2);
                $this->assertMoneyRange($amountReceived);
                $transactionNumber = 'BM-'.$branch->code.'-'.Str::upper((string) Str::ulid());
                $sale = Sale::query()->create([
                    'branch_id' => $branch->getKey(),
                    'cashier_id' => $cashier->getKey(),
                    'cashier_shift_id' => $shift->getKey(),
                    'transaction_number' => $transactionNumber,
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'status' => 'completed',
                    'payment_method' => 'cash',
                    'subtotal' => $subtotal,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountTotal,
                    'discount_reason' => $discountReason,
                    'tax_total' => $taxTotal,
                    'total' => $total,
                    'amount_received' => $amountReceived,
                    'change_due' => $changeDue,
                    'completed_at' => now($branch->timezone),
                ]);

                foreach ($saleLines as $line) {
                    $balance = $line['balance'];
                    $quantityAfter = bcsub((string) $balance->quantity_on_hand, $line['quantity'], 3);
                    $balance->quantity_on_hand = $quantityAfter;
                    $balance->save();

                    $sale->items()->create([
                        'product_id' => $line['product']->getKey(),
                        'product_sku' => $line['product']->sku,
                        'product_name' => $line['product']->name,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'subtotal' => $line['subtotal'],
                        'discount_amount' => $line['discount_amount'],
                        'tax_rate' => $line['tax_rate'],
                        'tax_amount' => $line['tax_amount'],
                        'total' => $line['total'],
                        'unit_cost' => $line['unit_cost'],
                        'cost_total' => $line['cost_total'],
                    ]);

                    InventoryMovement::query()->create([
                        'branch_id' => $branch->getKey(),
                        'product_id' => $line['product']->getKey(),
                        'user_id' => $cashier->getKey(),
                        'movement_type' => 'sale_out',
                        'quantity_delta' => bcsub('0', $line['quantity'], 3),
                        'quantity_after' => $quantityAfter,
                        'source_type' => 'sale',
                        'source_id' => $sale->getKey(),
                        'reason' => 'Penjualan '.$transactionNumber,
                    ]);
                }

                return $sale->load('items');
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existingSale($branch, $cashier, $idempotencyKey);
            if ($existing !== null) {
                return $this->resolveReplay($existing, $fingerprint);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $requestedItems
     * @return array<int, array{product_id: int, quantity: string}>
     */
    private function normalizeItems(array $requestedItems): array
    {
        if ($requestedItems === [] || count($requestedItems) > 100) {
            throw new InvalidArgumentException('Keranjang harus berisi 1 sampai 100 jenis produk.');
        }

        $quantities = [];
        foreach ($requestedItems as $item) {
            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = $item['quantity'] ?? null;

            if (! is_int($productId) || $productId < 1 || ! is_string($quantity) && ! is_int($quantity)) {
                throw new InvalidArgumentException('Data produk atau jumlah tidak valid.');
            }

            $quantity = (string) $quantity;
            if (! preg_match('/^\d{1,11}(?:\.\d{1,3})?$/D', $quantity)) {
                throw new InvalidArgumentException('Jumlah harus memiliki maksimal tiga digit desimal.');
            }

            $quantity = bcadd($quantity, '0', 3);
            if (bccomp($quantity, '0', 3) <= 0) {
                throw new InvalidArgumentException('Jumlah produk harus lebih dari nol.');
            }

            $quantities[$productId] = isset($quantities[$productId])
                ? bcadd($quantities[$productId], $quantity, 3)
                : $quantity;
        }

        ksort($quantities, SORT_NUMERIC);

        return array_map(
            fn (int $productId, string $quantity): array => ['product_id' => $productId, 'quantity' => $quantity],
            array_keys($quantities),
            array_values($quantities),
        );
    }

    private function normalizeMoney(string $amount, string $label): string
    {
        if (! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException("{$label} harus berupa nominal nonnegatif dengan maksimal dua digit desimal.");
        }

        return bcadd($amount, '0', 2);
    }

    private function normalizeDiscountPercent(string $percent): string
    {
        if (! preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $percent)) {
            throw new InvalidArgumentException('Persentase diskon tidak valid.');
        }

        $percent = bcadd($percent, '0', 2);
        if (bccomp($percent, '100.00', 2) > 0) {
            throw new InvalidArgumentException('Persentase diskon tidak boleh melebihi 100%.');
        }

        return $percent;
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
            throw new InvalidArgumentException('Nilai penjualan melampaui batas nominal yang didukung.');
        }
    }

    private function existingSale(Branch $branch, User $cashier, string $idempotencyKey): ?Sale
    {
        return Sale::query()
            ->where('branch_id', $branch->getKey())
            ->where('cashier_id', $cashier->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function resolveReplay(Sale $sale, string $fingerprint): Sale
    {
        if (! hash_equals($sale->request_fingerprint, $fingerprint)) {
            throw new IdempotencyConflictException;
        }

        return $sale->load('items');
    }
}
