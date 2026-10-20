@extends('layouts.guest')

@section('title', 'Tambah produk')

@section('content')
<main class="mx-auto min-h-screen max-w-4xl px-5 py-8 sm:px-8 sm:py-12">
    <header class="flex items-center justify-between gap-4"><a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span><span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span></a><a href="{{ route('products.index') }}" class="text-sm font-semibold text-stone-600 hover:text-emerald-800">Kembali ke katalog</a></header>

    <section class="mt-10">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-800">{{ $branch->name }} · Katalog</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">Tambah produk</h1>
        <p class="mt-2 text-sm leading-6 text-stone-600">Informasi produk disimpan di katalog bersama; harga jual, batas minimum, dan saldo awal disiapkan untuk cabang aktif ini.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><p class="font-semibold">Periksa kembali data berikut:</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('products.store') }}" class="mt-7 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-8">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="name" class="block text-sm font-semibold">Nama produk <span class="text-red-700">*</span></label><input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autofocus class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></div>
                <div><label for="sku" class="block text-sm font-semibold">SKU <span class="text-red-700">*</span></label><input id="sku" name="sku" value="{{ old('sku') }}" required maxlength="64" autocomplete="off" class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 uppercase outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"><p class="mt-1 text-xs text-stone-500">Huruf, angka, tanda hubung, atau garis bawah.</p></div>
                <div><label for="barcode" class="block text-sm font-semibold">Barcode</label><input id="barcode" name="barcode" value="{{ old('barcode') }}" maxlength="64" inputmode="numeric" class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"><p class="mt-1 text-xs text-stone-500">Opsional; harus unik bila diisi.</p></div>
                <div><label for="category_id" class="block text-sm font-semibold">Kategori</label><select id="category_id" name="category_id" class="mt-2 w-full rounded-xl border border-stone-300 bg-white px-3.5 py-3 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"><option value="">Tanpa kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="unit_of_measure" class="block text-sm font-semibold">Satuan <span class="text-red-700">*</span></label><input id="unit_of_measure" name="unit_of_measure" value="{{ old('unit_of_measure', 'pcs') }}" required maxlength="32" class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></div>
                <div><label for="purchase_cost" class="block text-sm font-semibold">Harga pokok (Rp) <span class="text-red-700">*</span></label><input id="purchase_cost" name="purchase_cost" type="number" min="0" step="0.01" value="{{ old('purchase_cost', '0.00') }}" required class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 tabular-nums outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></div>
                <div><label for="selling_price" class="block text-sm font-semibold">Harga jual cabang (Rp) <span class="text-red-700">*</span></label><input id="selling_price" name="selling_price" type="number" min="0" step="0.01" value="{{ old('selling_price', '0.00') }}" required class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 tabular-nums outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></div>
                <div><label for="minimum_stock" class="block text-sm font-semibold">Peringatan stok minimum <span class="text-red-700">*</span></label><input id="minimum_stock" name="minimum_stock" type="number" min="0" step="0.001" value="{{ old('minimum_stock', '0') }}" required class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 tabular-nums outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></div>
                <div><label for="tax_rate" class="block text-sm font-semibold">Tarif pajak (%) <span class="text-red-700">*</span></label><input id="tax_rate" name="tax_rate" type="number" min="0" max="100" step="0.01" value="{{ old('tax_rate', '0') }}" required class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 tabular-nums outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></div>
                <div class="sm:col-span-2"><label for="description" class="block text-sm font-semibold">Deskripsi</label><textarea id="description" name="description" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border border-stone-300 px-3.5 py-3 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10">{{ old('description') }}</textarea></div>
            </div>
            <div class="mt-7 flex flex-wrap items-center justify-end gap-3 border-t border-stone-100 pt-5"><a href="{{ route('products.index') }}" class="rounded-xl px-4 py-3 text-sm font-semibold text-stone-600 hover:bg-stone-100">Batal</a><button type="submit" class="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-800">Simpan produk</button></div>
        </form>
    </section>
</main>
@endsection
