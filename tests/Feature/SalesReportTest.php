<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_sales_report_aggregates_branch_sales_and_costs_for_the_selected_dates(): void
    {
        [$branch, $user] = $this->reportUser();
        $otherBranch = Branch::query()->create(['code' => 'BDG-RPT', 'name' => 'Cabang Laporan Lain']);
        $product = Product::query()->create([
            'sku' => 'RPT-001',
            'name' => 'Barang Laporan',
            'purchase_cost' => '300.00',
            'default_selling_price' => '500.00',
        ]);
        $this->createSale($branch, $user, $product, 'BM-REPORT-1', '500.00', '55.00', '555.00', '300.00', '2026-10-20 10:00:00');
        $this->createSale($branch, $user, $product, 'BM-REPORT-2', '1000.00', '110.00', '1110.00', '600.00', '2026-10-19 12:00:00');
        $this->createSale($otherBranch, $user, $product, 'BM-REPORT-OTHER', '700.00', '77.00', '777.00', '400.00', '2026-10-20 11:00:00');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.sales', ['from' => '2026-10-19', 'to' => '2026-10-20']))
            ->assertOk()
            ->assertDontSee('BM-REPORT-OTHER')
            ->assertViewHas('summary.sale_count', 2)
            ->assertSee('Rp 1.500')
            ->assertSee('Rp 165')
            ->assertSee('Rp 1.665')
            ->assertSee('Rp 900')
            ->assertSee('Rp 600');
    }

    public function test_sales_report_formats_a_negative_gross_profit_without_float_arithmetic(): void
    {
        [$branch, $user] = $this->reportUser();
        $product = Product::query()->create([
            'sku' => 'RPT-LOSS',
            'name' => 'Barang Margin Negatif',
            'purchase_cost' => '150.00',
            'default_selling_price' => '100.00',
        ]);
        $this->createSale($branch, $user, $product, 'BM-REPORT-LOSS', '100.00', '11.00', '111.00', '150.00', '2026-10-20 10:00:00');

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.sales', ['from' => '2026-10-20', 'to' => '2026-10-20']))
            ->assertOk()
            ->assertSee('-Rp 50');
    }

    public function test_sales_report_rejects_a_date_range_over_31_days(): void
    {
        [$branch, $user] = $this->reportUser();

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.sales', ['from' => '2026-01-01', 'to' => '2026-02-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_staff_without_sales_report_permission_cannot_view_the_report(): void
    {
        $branch = Branch::query()->create(['code' => 'JKT-NORPT', 'name' => 'Cabang Tanpa Laporan']);
        $user = User::factory()->create();
        $user->branches()->attach($branch);

        $this->actingAs($user)
            ->withSession(['active_branch_id' => $branch->id])
            ->get(route('reports.sales'))
            ->assertForbidden();
    }

    public function test_unauthenticated_staff_are_redirected_from_the_sales_report(): void
    {
        $this->get(route('reports.sales'))->assertRedirect(route('login'));
    }

    private function reportUser(): array
    {
        $branch = Branch::query()->create(['code' => 'JKT-RPT', 'name' => 'Cabang Laporan']);
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'reporter', 'label' => 'Pelapor']);
        $permission = Permission::query()->create(['name' => 'reports.sales', 'label' => 'Lihat laporan penjualan']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $user->branches()->attach($branch);

        return [$branch, $user];
    }

    private function createSale(
        Branch $branch,
        User $cashier,
        Product $product,
        string $transactionNumber,
        string $subtotal,
        string $tax,
        string $total,
        string $costTotal,
        string $completedAt,
    ): Sale {
        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'transaction_number' => $transactionNumber,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', $transactionNumber),
            'status' => 'completed',
            'payment_method' => 'cash',
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => $total,
            'amount_received' => $total,
            'change_due' => '0.00',
            'completed_at' => $completedAt,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => '1.000',
            'unit_price' => $subtotal,
            'subtotal' => $subtotal,
            'tax_rate' => '11.00',
            'tax_amount' => $tax,
            'total' => $total,
            'unit_cost' => $costTotal,
            'cost_total' => $costTotal,
        ]);

        return $sale;
    }
}
