<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $request->attributes->get('activeBranch');
        $search = trim((string) ($request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ])['q'] ?? ''));

        $products = Product::query()
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->where('branch_products.branch_id', $branch->getKey())
            ->where('branch_products.is_available', true)
            ->where('products.is_active', true)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw('LOWER(products.name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(products.sku) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(products.barcode) LIKE ?', [$pattern]);
                });
            })
            ->select([
                'products.*',
                'branch_products.minimum_stock as branch_minimum_stock',
            ])
            ->selectRaw('CAST(branch_products.selling_price AS VARCHAR) AS branch_selling_price')
            ->with([
                'category',
                'inventoryBalances' => fn ($query) => $query->where('branch_id', $branch->getKey()),
            ])
            ->orderBy('products.name')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', compact('branch', 'products', 'search'));
    }

    public function create(Request $request): View
    {
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('products.create', [
            'branch' => $request->attributes->get('activeBranch'),
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sku = $request->input('sku');
        $barcode = $request->input('barcode');
        $request->merge([
            'sku' => is_string($sku) ? mb_strtoupper(trim($sku)) : $sku,
            'barcode' => is_string($barcode) && trim($barcode) === ''
                ? null
                : (is_string($barcode) ? trim($barcode) : $barcode),
        ]);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'alpha_dash:ascii', Rule::unique('products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit_of_measure' => ['required', 'string', 'max:32'],
            'purchase_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'minimum_stock' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:99999999999.999'],
            'tax_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        ]);

        $branch = $request->attributes->get('activeBranch');

        DB::transaction(function () use ($validated, $branch): void {
            $product = Product::query()->create([
                'category_id' => $validated['category_id'] ?? null,
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'unit_of_measure' => $validated['unit_of_measure'],
                'purchase_cost' => $validated['purchase_cost'],
                'default_selling_price' => $validated['selling_price'],
                'tax_rate' => $validated['tax_rate'],
                'is_active' => true,
            ]);

            $product->branches()->attach($branch, [
                'selling_price' => $validated['selling_price'],
                'purchase_cost' => $validated['purchase_cost'],
                'minimum_stock' => $validated['minimum_stock'],
                'is_available' => true,
            ]);

            InventoryBalance::query()->create([
                'branch_id' => $branch->getKey(),
                'product_id' => $product->getKey(),
                'quantity_on_hand' => '0.000',
            ]);
        }, 3);

        return redirect()
            ->route('products.index')
            ->with('status', 'Produk berhasil ditambahkan ke katalog cabang.');
    }
}
