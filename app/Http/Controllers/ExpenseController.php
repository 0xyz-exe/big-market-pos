<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $request->attributes->get('activeBranch');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $expenses = Expense::query()
            ->forBranch($branch->id)
            ->with(['category', 'creator', 'decider'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('expenses.index', compact('branch', 'expenses'));
    }

    public function create(Request $request): View
    {
        return view('expenses.create', [
            'branch' => $request->attributes->get('activeBranch'),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('is_active', true),
            ],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'other'])],
            'receipt_reference' => ['nullable', 'string', 'max:120'],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $branch = $request->attributes->get('activeBranch');
        $receipt = $validated['receipt'] ?? null;
        unset($validated['receipt']);
        $receiptPath = null;

        if ($receipt !== null) {
            $receiptPath = $receipt->storeAs(
                'expense-receipts/'.$branch->id,
                Str::uuid().'.'.$receipt->extension(),
                'local',
            );
            if (! is_string($receiptPath)) {
                throw new RuntimeException('Bukti pengeluaran gagal disimpan.');
            }
        }

        try {
            $request->user()->expensesCreated()->create([
                ...$validated,
                'branch_id' => $branch->id,
                'status' => 'pending',
                'receipt_path' => $receiptPath,
                'receipt_original_name' => $receipt === null ? null : $this->safeOriginalName($receipt->getClientOriginalName()),
                'receipt_mime_type' => $receipt?->getMimeType(),
                'receipt_size_bytes' => $receipt?->getSize(),
            ]);
        } catch (Throwable $exception) {
            if ($receiptPath !== null) {
                Storage::disk('local')->delete($receiptPath);
            }

            throw $exception;
        }

        return redirect()->route('expenses.index')->with('status', 'Pengeluaran disimpan dan menunggu persetujuan.');
    }

    public function receipt(Request $request, Expense $expense)
    {
        $this->assertActiveBranch($request, $expense);
        abort_unless($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->download($expense->receipt_path, $expense->receipt_original_name ?? 'receipt', [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->assertActiveBranch($request, $expense);

        $result = DB::transaction(function () use ($request, $expense): string {
            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $this->assertActiveBranch($request, $locked);

            if ($locked->status !== 'pending') {
                return 'already_decided';
            }
            if ($locked->created_by === $request->user()->id) {
                return 'self_approval';
            }

            $locked->forceFill([
                'status' => 'approved',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => null,
            ])->save();

            return 'approved';
        });

        if ($result === 'approved') {
            return redirect()->route('expenses.index')->with('status', 'Pengeluaran disetujui.');
        }

        return redirect()->route('expenses.index')->withErrors([
            'expense' => $result === 'self_approval'
                ? 'Pengaju tidak boleh menyetujui pengeluaran sendiri.'
                : 'Pengeluaran ini sudah diputuskan dan tidak dapat diubah.',
        ]);
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->assertActiveBranch($request, $expense);

        $result = DB::transaction(function () use ($request, $expense, $validated): string {
            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $this->assertActiveBranch($request, $locked);

            if ($locked->status !== 'pending') {
                return 'already_decided';
            }

            $locked->forceFill([
                'status' => 'rejected',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $validated['decision_note'],
            ])->save();

            return 'rejected';
        });

        return $result === 'rejected'
            ? redirect()->route('expenses.index')->with('status', 'Pengeluaran ditolak dan alasannya dicatat.')
            : redirect()->route('expenses.index')->withErrors(['expense' => 'Pengeluaran ini sudah diputuskan dan tidak dapat diubah.']);
    }

    private function assertActiveBranch(Request $request, Expense $expense): void
    {
        if ($expense->branch_id !== $request->attributes->get('activeBranch')->id) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$expense->id]);
        }
    }

    private function safeOriginalName(string $originalName): string
    {
        $basename = basename(str_replace('\\', '/', $originalName));
        $safe = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename) ?: 'receipt';

        return mb_substr($safe, 0, 255);
    }
}
