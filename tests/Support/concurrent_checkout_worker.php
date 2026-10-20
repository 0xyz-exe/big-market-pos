<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\User;
use App\Services\SaleCheckoutService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
fwrite(STDOUT, "READY\n");

if (trim((string) fgets(STDIN)) !== 'GO') {
    fwrite(STDERR, "Worker did not receive start signal.\n");
    exit(2);
}

try {
    $branch = Branch::query()->findOrFail((int) $argv[1]);
    $cashier = User::query()->findOrFail((int) $argv[2]);
    $productId = (int) $argv[3];
    $idempotencyKey = $argv[4];

    $sale = app(SaleCheckoutService::class)->checkout(
        $branch,
        $cashier,
        [['product_id' => $productId, 'quantity' => '4.000']],
        '10000.00',
        $idempotencyKey,
    );

    fwrite(STDOUT, "COMPLETED:{$sale->id}\n");
} catch (InsufficientStockException) {
    fwrite(STDOUT, "INSUFFICIENT_STOCK\n");
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1);
}
