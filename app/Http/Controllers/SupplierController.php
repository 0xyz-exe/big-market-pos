<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::query()->orderByDesc('is_active')->orderBy('name')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $request->merge(['code' => is_string($code) ? mb_strtoupper(trim($code)) : $code]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique('suppliers', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
        ]);
        Supplier::query()->create([...$validated, 'is_active' => true]);

        return redirect()->route('suppliers.index')->with('status', 'Pemasok ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('status', 'Pemasok diperbarui.');
    }
}
