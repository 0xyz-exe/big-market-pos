@extends('layouts.guest')

@section('title', 'Buat Pesanan Pembelian')

@section('content')
<main class="mx-auto min-h-screen max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
    <header class="flex flex-wrap items-center justify-between gap-4"><a href="{{ route('purchases.index') }}" class="inline-flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span><span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span></a><a href="{{ route('purchases.index') }}" class="text-sm font-semibold text-stone-600 hover:text-emerald-800">Kembali ke pesanan</a></header>
    <section class="mt-10"><p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-800">Pengadaan · {{ $branch->name }}</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Buat pesanan pembelian</h1><p class="mt-2 text-sm text-stone-600">Ini mencatat pesanan internal; sistem tidak mengirim purchase order ke pemasok dan tidak mencatat pembayaran.</p>
        @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($suppliers->isEmpty())<div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">Tambahkan pemasok aktif terlebih dahulu di <a class="font-bold underline" href="{{ route('suppliers.index') }}">Data pemasok</a>.</div>@elseif($products->isEmpty())<div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">Belum ada produk aktif yang terdaftar pada cabang ini.</div>@else
        <form method="POST" action="{{ route('purchases.store') }}" class="mt-6 space-y-5 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">@csrf
            <div><label for="supplier-id" class="mb-1 block text-sm font-semibold">Pemasok</label><select id="supplier-id" name="supplier_id" required class="w-full rounded-xl border border-stone-300 px-3 py-2.5"><option value="">Pilih pemasok</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>@endforeach</select></div>
            <fieldset class="space-y-3"><legend class="text-sm font-bold">Produk pesanan</legend><div id="purchase-items" class="space-y-3"><div class="grid gap-3 rounded-xl border border-stone-200 p-3 sm:grid-cols-2 lg:grid-cols-4"><div class="sm:col-span-2 lg:col-span-2"><label for="product-0" class="mb-1 block text-xs font-semibold">Produk</label><select id="product-0" name="items[0][product_id]" required class="purchase-product w-full rounded-lg border border-stone-300 px-3 py-2"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}" data-cost="{{ $product->purchase_cost ?? $product->catalog_purchase_cost }}" @selected(old('items.0.product_id') == $product->id)>{{ $product->sku }} · {{ $product->name }}</option>@endforeach</select></div><div><label for="quantity-0" class="mb-1 block text-xs font-semibold">Jumlah</label><input id="quantity-0" type="text" inputmode="decimal" name="items[0][quantity_ordered]" value="{{ old('items.0.quantity_ordered', '1.000') }}" required pattern="\d{1,11}(\.\d{1,3})?" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div><div><label for="unit-cost-0" class="mb-1 block text-xs font-semibold">Harga beli per unit (Rp)</label><input id="unit-cost-0" type="text" inputmode="decimal" name="items[0][unit_cost]" value="{{ old('items.0.unit_cost') }}" required pattern="\d{1,12}(\.\d{1,2})?" class="purchase-cost w-full rounded-lg border border-stone-300 px-3 py-2"></div></div></div><button id="add-purchase-item" type="button" class="rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold">+ Tambah produk</button></fieldset>
            <div><label for="purchase-notes" class="mb-1 block text-sm font-semibold">Catatan</label><textarea id="purchase-notes" name="notes" rows="3" maxlength="2000" class="w-full rounded-xl border border-stone-300 px-3 py-2.5">{{ old('notes') }}</textarea></div>
            <div class="flex flex-wrap gap-3"><button class="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white">Catat pesanan pembelian</button><a href="{{ route('purchases.index') }}" class="rounded-xl border border-stone-300 px-5 py-3 text-sm font-semibold">Batal</a></div>
        </form>
        <template id="purchase-item-template"><div class="grid gap-3 rounded-xl border border-stone-200 p-3 sm:grid-cols-2 lg:grid-cols-4"><div class="sm:col-span-2 lg:col-span-2"><label data-label="product" class="mb-1 block text-xs font-semibold">Produk</label><select data-field="product_id" required class="purchase-product w-full rounded-lg border border-stone-300 px-3 py-2"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}" data-cost="{{ $product->purchase_cost ?? $product->catalog_purchase_cost }}">{{ $product->sku }} · {{ $product->name }}</option>@endforeach</select></div><div><label data-label="quantity" class="mb-1 block text-xs font-semibold">Jumlah</label><input data-field="quantity_ordered" type="text" inputmode="decimal" value="1.000" required pattern="\d{1,11}(\.\d{1,3})?" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div><div><label data-label="cost" class="mb-1 block text-xs font-semibold">Harga beli per unit (Rp)</label><input data-field="unit_cost" type="text" inputmode="decimal" required pattern="\d{1,12}(\.\d{1,2})?" class="purchase-cost w-full rounded-lg border border-stone-300 px-3 py-2"></div><button type="button" class="remove-purchase-item justify-self-start text-sm font-semibold text-red-700">Hapus produk</button></div></template>
    @endif
    </section>
</main>
<script>
(() => {
    const list = document.getElementById('purchase-items');
    const template = document.getElementById('purchase-item-template');
    const addButton = document.getElementById('add-purchase-item');
    if (!list || !template || !addButton) return;
    let nextIndex = 1;
    const bindRow = (row) => {
        const product = row.querySelector('.purchase-product');
        const cost = row.querySelector('.purchase-cost');
        product.addEventListener('change', () => { cost.value = product.selectedOptions[0]?.dataset.cost || ''; });
        row.querySelector('.remove-purchase-item')?.addEventListener('click', () => row.remove());
    };
    bindRow(list.firstElementChild);
    addButton.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        for (const [field, name] of Object.entries({product_id: 'Produk', quantity_ordered: 'Jumlah', unit_cost: 'Harga beli'})) {
            const input = row.querySelector(`[data-field="${field}"]`);
            const id = `purchase-${field}-${nextIndex}`;
            input.id = id;
            input.name = `items[${nextIndex}][${field}]`;
            row.querySelector(`[data-label="${field === 'product_id' ? 'product' : field === 'quantity_ordered' ? 'quantity' : 'cost'}"]`).htmlFor = id;
        }
        list.append(row);
        bindRow(row);
        nextIndex++;
    });
})();
</script>
@endsection
