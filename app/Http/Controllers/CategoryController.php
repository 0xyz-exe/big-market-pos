<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('categories.index', [
            'branch' => $request->attributes->get('activeBranch'),
            'categories' => Category::query()->orderByDesc('is_active')->orderBy('name')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validateCategory($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
            'slug' => ['required', 'string', 'max:120', Rule::unique('categories', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Category::query()->create([...$validated, 'is_active' => true]);

        return redirect()->route('categories.index')->with('status', 'Kategori produk ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->validateCategory($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'slug' => ['required', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(fn () => $category->update($validated));

        return redirect()->route('categories.index')->with('status', 'Kategori produk diperbarui.');
    }

    private function validateCategory(Request $request): void
    {
        $name = is_string($request->input('name')) ? trim($request->input('name')) : '';
        $request->merge(['name' => $name, 'slug' => Str::slug($name)]);
    }
}
