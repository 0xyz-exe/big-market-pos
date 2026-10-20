<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Branch $branch */
        $branch = $request->attributes->get('activeBranch');
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $today = now($branch->timezone)->format('Y-m-d');
        $from = $validated['from'] ?? $validated['to'] ?? $today;
        $to = $validated['to'] ?? $validated['from'] ?? $today;
        $timezone = new DateTimeZone($branch->timezone);
        $start = new DateTimeImmutable($from.' 00:00:00', $timezone);
        $endExclusive = (new DateTimeImmutable($to.' 00:00:00', $timezone))->modify('+1 day');

        if ($start->diff($endExclusive)->days > 31) {
            throw ValidationException::withMessages(['to' => 'Rentang laporan maksimal 31 hari.']);
        }

        $summary = [
            'sale_count' => 0,
            'subtotal' => '0.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '0.00',
            'cost_total' => '0.00',
            'gross_profit' => '0.00',
        ];
        $dailyTotals = [];

        foreach ($this->salesInRange($branch, $start, $endExclusive)->orderBy('completed_at')->cursor() as $sale) {
            $date = $sale->completed_at->timezone($branch->timezone)->format('Y-m-d');
            $dailyTotals[$date] ??= [
                'date' => $date,
                'sale_count' => 0,
                'subtotal' => '0.00',
                'discount_total' => '0.00',
                'tax_total' => '0.00',
                'total' => '0.00',
            ];
            $summary['sale_count']++;
            $dailyTotals[$date]['sale_count']++;

            foreach (['subtotal', 'discount_total', 'tax_total', 'total'] as $field) {
                $summary[$field] = bcadd($summary[$field], (string) $sale->{$field}, 2);
                $dailyTotals[$date][$field] = bcadd($dailyTotals[$date][$field], (string) $sale->{$field}, 2);
            }
        }

        foreach (SaleItem::query()
            ->whereIn('sale_id', $this->salesInRange($branch, $start, $endExclusive)->select('id'))
            ->orderBy('id')
            ->cursor() as $item) {
            $summary['cost_total'] = bcadd($summary['cost_total'], (string) $item->cost_total, 2);
        }

        $summary['gross_profit'] = bcsub(bcsub($summary['subtotal'], $summary['discount_total'], 2), $summary['cost_total'], 2);
        krsort($dailyTotals);

        return view('reports.sales', [
            'branch' => $branch,
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'dailyTotals' => array_values($dailyTotals),
            'reportRoute' => 'reports.sales',
            'isFinancialReport' => false,
        ]);
    }

    public function financial(Request $request): View
    {
        $salesView = $this->index($request);
        $data = $salesView->getData();
        /** @var Branch $branch */
        $branch = $data['branch'];
        $expenseTotal = '0.00';
        $approvedExpenseCount = 0;
        $dailyExpenseTotals = [];
        $categoryTotals = [];
        $toExclusive = (new DateTimeImmutable($data['to']))->modify('+1 day')->format('Y-m-d');

        Expense::query()
            ->forBranch($branch->getKey())
            ->where('status', 'approved')
            ->where('expense_date', '>=', $data['from'])
            ->where('expense_date', '<', $toExclusive)
            ->with('category')
            ->orderBy('id')
            ->chunkById(500, function ($expenses) use (&$expenseTotal, &$approvedExpenseCount, &$dailyExpenseTotals, &$categoryTotals): void {
                foreach ($expenses as $expense) {
                    $date = $expense->expense_date->format('Y-m-d');
                    $expenseTotal = bcadd($expenseTotal, (string) $expense->amount, 2);
                    $approvedExpenseCount++;
                    $dailyExpenseTotals[$date] ??= ['date' => $date, 'count' => 0, 'amount' => '0.00'];
                    $dailyExpenseTotals[$date]['count']++;
                    $dailyExpenseTotals[$date]['amount'] = bcadd($dailyExpenseTotals[$date]['amount'], (string) $expense->amount, 2);

                    $categoryId = $expense->expense_category_id;
                    $categoryTotals[$categoryId] ??= [
                        'name' => $expense->category->name,
                        'count' => 0,
                        'amount' => '0.00',
                    ];
                    $categoryTotals[$categoryId]['count']++;
                    $categoryTotals[$categoryId]['amount'] = bcadd($categoryTotals[$categoryId]['amount'], (string) $expense->amount, 2);
                }
            });

        krsort($dailyExpenseTotals);
        usort($categoryTotals, fn (array $left, array $right): int => bccomp($right['amount'], $left['amount'], 2));
        $data['financialSummary'] = [
            ...$data['summary'],
            'expense_total' => $expenseTotal,
            'approved_expense_count' => $approvedExpenseCount,
            'contribution_after_expenses' => bcsub($data['summary']['gross_profit'], $expenseTotal, 2),
        ];
        $data['dailyExpenseTotals'] = array_values($dailyExpenseTotals);
        $data['expenseCategoryTotals'] = array_values($categoryTotals);
        $data['reportRoute'] = 'reports.financial';
        $data['isFinancialReport'] = true;

        return view('reports.sales', $data);
    }

    private function salesInRange(Branch $branch, DateTimeImmutable $start, DateTimeImmutable $endExclusive): Builder
    {
        return Sale::query()
            ->where('branch_id', $branch->getKey())
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $endExclusive);
    }
}
