<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAdjustmentService
{
    public function adjust(
        Branch $branch,
        Product $product,
        string $quantityDelta,
        string $reason,
        User $actor,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): InventoryBalance {
        if (! preg_match('/^-?\d+(?:\.\d{1,3})?$/D', $quantityDelta)) {
            throw new InvalidArgumentException('Jumlah harus berupa angka desimal dengan maksimal tiga digit pecahan.');
        }

        $delta = bcadd($quantityDelta, '0', 3);
        if (bccomp($delta, '0', 3) === 0) {
            throw new InvalidArgumentException('Jumlah penyesuaian tidak boleh nol.');
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('Alasan penyesuaian wajib diisi dan maksimal 255 karakter.');
        }

        if (($sourceType === null) !== ($sourceId === null)) {
            throw new InvalidArgumentException('Jenis dan ID sumber harus diberikan secara bersamaan.');
        }

        if (! $actor->canAccessBranch($branch) || ! $actor->hasPermission('inventory.adjust')) {
            throw new AuthorizationException('Anda tidak berwenang menyesuaikan persediaan cabang ini.');
        }

        if (! $branch->is_active) {
            throw new AuthorizationException('Cabang tidak aktif.');
        }

        return DB::transaction(function () use ($branch, $product, $delta, $reason, $actor, $sourceType, $sourceId): InventoryBalance {
            $isAssigned = DB::table('branch_products')
                ->where('branch_id', $branch->getKey())
                ->where('product_id', $product->getKey())
                ->exists();

            if (! $isAssigned) {
                throw (new ModelNotFoundException)->setModel(Product::class, [$product->getKey()]);
            }

            $balance = InventoryBalance::query()
                ->where('branch_id', $branch->getKey())
                ->where('product_id', $product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $quantityAfter = bcadd((string) $balance->quantity_on_hand, $delta, 3);
            if (bccomp($quantityAfter, '0', 3) < 0) {
                throw new InsufficientStockException('Penyesuaian tidak dapat menghasilkan stok negatif.');
            }

            if (bccomp($quantityAfter, '99999999999.999', 3) > 0) {
                throw new InvalidArgumentException('Saldo persediaan melebihi batas kolom yang didukung.');
            }

            $balance->quantity_on_hand = $quantityAfter;
            $balance->save();

            InventoryMovement::query()->create([
                'branch_id' => $branch->getKey(),
                'product_id' => $product->getKey(),
                'user_id' => $actor->getKey(),
                'movement_type' => bccomp($delta, '0', 3) > 0 ? 'adjustment_in' : 'adjustment_out',
                'quantity_delta' => $delta,
                'quantity_after' => $quantityAfter,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reason' => $reason,
            ]);

            return $balance->refresh();
        }, 3);
    }
}
