<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('expense-categories.index', [
            'branch' => $request->attributes->get('activeBranch'),
            'categories' => ExpenseCategory::query()->orderByDesc('is_active')->orderBy('name')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')],
        ]);
        ExpenseCategory::query()->create([...$validated, 'is_active' => true]);

        return redirect()->route('expense-categories.index')->with('status', 'Kategori pengeluaran ditambahkan.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        $expenseCategory->update($validated);

        return redirect()->route('expense-categories.index')->with('status', 'Kategori pengeluaran diperbarui.');
    }
}
