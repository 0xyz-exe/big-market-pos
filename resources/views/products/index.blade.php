@extends('layouts.guest')

@section('title', 'Produk')

@section('content')
<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="flex flex-col border-b border-stone-200 bg-white px-5 py-5 lg:min-h-screen lg:border-b-0 lg:border-r lg:px-6">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700"><span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span><span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span></a>
        <p class="mt-10 text-xs font-bold uppercase tracking-[0.16em] text-stone-400">Menu utama</p>
        <nav class="mt-3 space-y-1" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Ringkasan</a>
            <a href="{{ route('products.index') }}" aria-current="page" class="block rounded-xl bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-900">Produk &amp; stok</a>
            @if (auth()->user()->hasPermission('sales.create'))<a href="{{ route('pos.index') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Kasir POS</a>@endif
            <span class="block rounded-xl px-3 py-2.5 text-sm text-stone-400" aria-disabled="true">Laporan · Segera</span>
        </nav>
        <div class="mt-auto hidden border-t border-stone-200 pt-5 lg:block"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-stone-500">{{ $branch->name }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-sm font-semibold text-stone-600 hover:text-red-700">Keluar</button></form></div>
    </aside>

    <main class="min-w-0 px-5 py-7 sm:px-8 lg:px-10 lg:py-10">
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-stone-200 pb-6">
            <div><p class="text-sm text-stone-500"><a href="{{ route('dashboard') }}" class="hover:underline">{{ $branch->name }}</a> / Katalog</p><h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Produk</h1><p class="mt-1 text-sm text-stone-600">Produk aktif yang tersedia di cabang ini.</p></div>
            <div class="flex items-center gap-2">@if(auth()->user()->hasPermission('products.manage'))<a href="{{ route('categories.index') }}" class="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-stone-50">Kategori</a><a href="{{ route('products.create') }}" class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-800">Tambah produk</a>@endif</div>
        </header>

        @if (session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif

        <form method="GET" action="{{ route('products.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row">
            <label for="q" class="sr-only">Cari nama, SKU, atau barcode</label>
            <input id="q" name="q" value="{{ $search }}" maxlength="120" placeholder="Cari nama, SKU, atau barcode…" class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10">
            <button class="rounded-xl border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-700 hover:bg-stone-100">Cari produk</button>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            @if ($products->isEmpty())
                <div class="px-6 py-14 text-center"><p class="font-semibold">{{ $search === '' ? 'Belum ada produk di cabang ini' : 'Produk tidak ditemukan' }}</p><p class="mt-2 text-sm text-stone-600">{{ $search === '' ? 'Tambahkan produk untuk mulai menyiapkan katalog cabang.' : 'Coba kata kunci, SKU, atau barcode lain.' }}</p></div>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-stone-50 text-xs uppercase tracking-wider text-stone-500"><tr><th scope="col" class="px-5 py-3 font-semibold">Produk</th><th scope="col" class="px-4 py-3 font-semibold">Kategori</th><th scope="col" class="px-4 py-3 text-right font-semibold">Harga jual</th><th scope="col" class="px-4 py-3 text-right font-semibold">Stok</th><th scope="col" class="px-5 py-3 text-right font-semibold">Batas minimum</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($products as $product)
                            @php($balance = $product->inventoryBalances->first())
                            <tr class="hover:bg-stone-50/70"><td class="px-5 py-4"><span class="block font-semibold">{{ $product->name }}</span><span class="mt-1 block text-xs text-stone-500">{{ $product->sku }}@if($product->barcode) · {{ $product->barcode }}@endif</span></td><td class="px-4 py-4 text-stone-600">{{ $product->category?->name ?? '—' }}</td><td class="px-4 py-4 text-right tabular-nums">{{ \App\Support\IdrFormatter::format((string) $product->branch_selling_price) }}</td><td class="px-4 py-4 text-right tabular-nums">{{ number_format((float) ($balance?->quantity_on_hand ?? 0), 3, ',', '.') }} {{ $product->unit_of_measure }}</td><td class="px-5 py-4 text-right tabular-nums text-stone-600">{{ number_format((float) $product->branch_minimum_stock, 3, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
                <div class="border-t border-stone-100 px-5 py-4">{{ $products->links() }}</div>
            @endif
        </div>
    </main>
</div>
@endsection
