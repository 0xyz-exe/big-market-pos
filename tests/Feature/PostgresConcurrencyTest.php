<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class PostgresConcurrencyTest extends TestCase
{
    public function test_postgresql_row_lock_blocks_a_second_connection_until_the_first_transaction_commits(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires a real PostgreSQL connection.');
        }

        $config = config('database.connections.pgsql');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['sslmode'] ?? 'prefer',
        );
        $first = DB::connection('pgsql')->getPdo();
        $second = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $table = 'lock_probe_'.bin2hex(random_bytes(8));
        $quotedTable = '"'.$table.'"';

        try {
            $first->exec("CREATE TABLE {$quotedTable} (id integer PRIMARY KEY, value integer NOT NULL)");
            $first->exec("INSERT INTO {$quotedTable} (id, value) VALUES (1, 0)");
            $second->exec("SET lock_timeout = '200ms'");

            $first->beginTransaction();
            $first->query("SELECT id FROM {$quotedTable} WHERE id = 1 FOR UPDATE")->fetchColumn();

            try {
                $second->query("SELECT id FROM {$quotedTable} WHERE id = 1 FOR UPDATE")->fetchColumn();
                $this->fail('A second connection acquired a row lock before the first transaction committed.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', (string) $exception->getCode());
            }

            $first->commit();
            $this->assertSame(1, (int) $second->query("SELECT id FROM {$quotedTable} WHERE id = 1 FOR UPDATE")->fetchColumn());
        } finally {
            if ($first->inTransaction()) {
                $first->rollBack();
            }

            $first->exec("DROP TABLE IF EXISTS {$quotedTable}");
            $second = null;
        }
    }

    public function test_concurrent_checkouts_cannot_oversell_a_shared_inventory_balance(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires a real PostgreSQL connection.');
        }

        $suffix = strtoupper(Str::random(8));
        $branch = Branch::query()->create(['code' => 'C'.$suffix, 'name' => 'Concurrent checkout test']);
        $cashier = User::factory()->create();
        $role = Role::query()->create(['name' => 'concurrency-'.$suffix, 'label' => 'Kasir uji konkurensi']);
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'sales.create'],
            ['label' => 'Buat penjualan'],
        );
        $permissionWasCreated = $permission->wasRecentlyCreated;
        $shiftPermission = Permission::query()->firstOrCreate(
            ['name' => 'shifts.manage'],
            ['label' => 'Kelola shift kasir'],
        );
        $shiftPermissionWasCreated = $shiftPermission->wasRecentlyCreated;
        $role->permissions()->attach([$permission->id, $shiftPermission->id]);
        $cashier->roles()->attach($role);
        $cashier->branches()->attach($branch);
        $shift = CashierShift::query()->create([
            'branch_id' => $branch->id,
            'user_id' => $cashier->id,
            'open_user_id' => $cashier->id,
            'status' => 'open',
            'opening_cash' => '0.00',
            'opened_at' => now($branch->timezone),
        ]);
        $product = Product::query()->create([
            'sku' => 'C'.$suffix,
            'name' => 'Produk uji konkurensi',
            'purchase_cost' => '50.00',
            'default_selling_price' => '100.00',
            'tax_rate' => '0.00',
        ]);
        $product->branches()->attach($branch, [
            'selling_price' => '100.00',
            'minimum_stock' => '0.000',
            'is_available' => true,
        ]);
        $balance = InventoryBalance::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '5.000',
        ]);

        $processes = [];
        $keys = [(string) Str::uuid(), (string) Str::uuid()];
        $worker = base_path('tests/Support/concurrent_checkout_worker.php');
        $connection = DB::connection('pgsql');

        try {
            foreach ($keys as $key) {
                $command = [
                    PHP_BINARY,
                    '-d', 'extension=pdo_pgsql',
                    '-d', 'extension=fileinfo',
                    '-d', 'extension=zip',
                    $worker,
                    (string) $branch->id,
                    (string) $cashier->id,
                    (string) $product->id,
                    $key,
                ];
                $pipes = [];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Could not start a concurrent checkout worker.');
                }

                $processes[] = ['process' => $process, 'pipes' => $pipes];
            }

            foreach ($processes as $entry) {
                $this->assertSame("READY\n", fgets($entry['pipes'][1]));
            }

            $connection->beginTransaction();
            $connection->table('inventory_balances')->where('id', $balance->id)->lockForUpdate()->first();
            foreach ($processes as $entry) {
                fwrite($entry['pipes'][0], "GO\n");
                fclose($entry['pipes'][0]);
            }

            // Give both separate PHP workers time to reach the row lock while it is held here.
            usleep(300_000);
            $connection->commit();

            $outputs = [];
            foreach ($processes as $entry) {
                $stdout = stream_get_contents($entry['pipes'][1]);
                $stderr = stream_get_contents($entry['pipes'][2]);
                fclose($entry['pipes'][1]);
                fclose($entry['pipes'][2]);
                $exitCode = proc_close($entry['process']);
                $this->assertSame(0, $exitCode, $stderr);
                $outputs[] = trim((string) $stdout);
            }
            $processes = [];

            $this->assertCount(1, array_filter($outputs, fn (string $output): bool => str_starts_with($output, 'COMPLETED:')));
            $this->assertCount(1, array_filter($outputs, fn (string $output): bool => $output === 'INSUFFICIENT_STOCK'));
            $this->assertSame(1, Sale::query()->where('branch_id', $branch->id)->count());
            $this->assertSame('1.000', $balance->fresh()->quantity_on_hand);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            foreach ($processes as $entry) {
                if (is_resource($entry['process'])) {
                    foreach ($entry['pipes'] as $pipe) {
                        if (is_resource($pipe)) {
                            fclose($pipe);
                        }
                    }
                    proc_terminate($entry['process']);
                    proc_close($entry['process']);
                }
            }

            $saleIds = Sale::query()->where('branch_id', $branch->id)->pluck('id');
            DB::table('inventory_movements')->where('source_type', 'sale')->whereIn('source_id', $saleIds)->delete();
            DB::table('sale_items')->whereIn('sale_id', $saleIds)->delete();
            DB::table('sales')->whereIn('id', $saleIds)->delete();
            $balance->delete();
            DB::table('cashier_shifts')->where('id', $shift->id)->delete();
            DB::table('branch_products')->where('branch_id', $branch->id)->delete();
            $product->delete();
            $cashier->roles()->detach($role);
            $cashier->branches()->detach($branch);
            $role->permissions()->detach($permission);
            $role->delete();
            if ($permissionWasCreated && ! $permission->roles()->exists()) {
                $permission->delete();
            }
            if ($shiftPermissionWasCreated && ! $shiftPermission->roles()->exists()) {
                $shiftPermission->delete();
            }
            $cashier->delete();
            $branch->delete();
        }
    }
}
