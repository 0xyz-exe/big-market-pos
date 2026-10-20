@extends('layouts.guest')

@section('title', 'Kasir POS')

@section('content')
<div class="min-h-screen bg-stone-50">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 bg-white px-5 py-4 sm:px-8">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Ringkasan</a>
            <h1 class="mt-1 text-xl font-bold tracking-tight">Kasir POS</h1>
            <p class="text-sm text-stone-600">{{ $branch->name }} · {{ $branch->code }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if (auth()->user()->hasPermission('shifts.manage'))
                <a href="{{ route('shifts.index') }}" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Shift</a>
            @endif
            @if (auth()->user()->hasPermission('sales.view'))
                <a href="{{ route('sales.index') }}" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Riwayat</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Keluar</button></form>
        </div>
    </header>

    <main class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.85fr)] lg:px-8">
        @if (! $openShift)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 lg:col-span-2"><h2 class="text-lg font-bold">Buka shift sebelum bertransaksi</h2><p class="mt-2 text-sm leading-6 text-stone-700">Kasir harus membuka shift dan mencatat uang awal yang tersedia sebelum checkout tunai.</p><a href="{{ route('shifts.index') }}" class="mt-4 inline-flex rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white">Buka halaman shift</a></section>
        @else
        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm" aria-labelledby="search-heading">
            <h2 id="search-heading" class="text-lg font-bold">Pilih produk</h2>
            <p class="mt-1 text-sm text-stone-600">Cari nama, SKU, atau pindai barcode dengan scanner USB.</p>
            <form id="search-form" class="mt-4 flex gap-2" data-search-url="{{ route('pos.products.search') }}">
                <label class="sr-only" for="product-search">Cari atau pindai barcode</label>
                <input id="product-search" autocomplete="off" autofocus maxlength="120" placeholder="Ketik nama / SKU atau pindai barcode" class="min-w-0 flex-1 rounded-xl border border-stone-300 px-4 py-3 text-sm focus:border-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-700/10">
                <button class="rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900">Cari</button>
            </form>
            <p id="search-status" class="mt-3 min-h-5 text-sm text-stone-600" role="status" aria-live="polite"></p>
            <div id="search-results" class="mt-2 space-y-2" aria-label="Hasil pencarian produk"></div>
        </section>

        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm" aria-labelledby="cart-heading">
            <div class="flex items-center justify-between gap-3">
                <h2 id="cart-heading" class="text-lg font-bold">Keranjang</h2>
                <span id="cart-count" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">0 item</span>
            </div>
            @if ($errors->any())
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                    <p class="font-semibold">Checkout belum berhasil:</p>
                    <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div id="cart-empty" class="mt-5 rounded-xl border border-dashed border-stone-300 px-4 py-10 text-center text-sm text-stone-500">Pindai barcode atau cari produk untuk memulai.</div>
            <div id="cart-lines" class="mt-4 space-y-3"></div>
            <dl class="mt-5 space-y-2 border-t border-stone-200 pt-4 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-stone-600">Subtotal</dt><dd id="subtotal" class="font-semibold tabular-nums">Rp 0</dd></div>
                @if (auth()->user()->hasPermission('sales.discount'))<div class="flex justify-between gap-3"><dt class="text-stone-600">Diskon</dt><dd id="discount-total" class="font-semibold tabular-nums">Rp 0</dd></div>@endif
                <div class="flex justify-between gap-3"><dt class="text-stone-600">Pajak</dt><dd id="tax-total" class="font-semibold tabular-nums">Rp 0</dd></div>
                <div class="flex justify-between gap-3 text-base"><dt class="font-bold">Total</dt><dd id="grand-total" class="font-bold tabular-nums">Rp 0</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-600">Kembalian (perkiraan)</dt><dd id="change-preview" class="font-semibold tabular-nums">—</dd></div>
            </dl>
            <form id="checkout-form" method="POST" action="{{ route('pos.checkout') }}" class="mt-5 border-t border-stone-200 pt-4">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <div id="checkout-lines"></div>
                @if (auth()->user()->hasPermission('sales.discount'))
                    <div class="mb-4 grid gap-3 sm:grid-cols-2"><div><label for="discount-percent" class="block text-sm font-semibold">Diskon (%)</label><input id="discount-percent" name="discount_percent" type="number" min="0" max="100" step="0.01" value="{{ old('discount_percent', '0.00') }}" class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2.5"></div><div><label for="discount-reason" class="block text-sm font-semibold">Alasan diskon</label><input id="discount-reason" name="discount_reason" maxlength="255" value="{{ old('discount_reason') }}" class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2.5"></div></div>
                @endif
                <label for="amount-received" class="block text-sm font-semibold">Uang diterima (Rp)</label>
                <input id="amount-received" name="amount_received" type="number" min="0" max="999999999999.99" step="0.01" inputmode="decimal" required value="{{ old('amount_received', '0.00') }}" class="mt-2 w-full rounded-xl border border-stone-300 px-4 py-3 text-right text-lg tabular-nums focus:border-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-700/10">
                <button id="checkout-button" type="submit" disabled class="mt-4 w-full rounded-xl bg-emerald-800 px-5 py-3.5 font-bold text-white hover:bg-emerald-900 disabled:cursor-not-allowed disabled:bg-stone-300">Selesaikan penjualan tunai</button>
                <p class="mt-2 text-center text-xs text-stone-500">Harga, pajak, stok, dan total dihitung ulang di server saat checkout.</p>
            </form>
        </section>
        @endif
    </main>
</div>

@if ($openShift)
<script>
(() => {
    const searchForm = document.querySelector('#search-form');
    const searchInput = document.querySelector('#product-search');
    const results = document.querySelector('#search-results');
    const searchStatus = document.querySelector('#search-status');
    const cartLines = document.querySelector('#cart-lines');
    const cartEmpty = document.querySelector('#cart-empty');
    const cartCount = document.querySelector('#cart-count');
    const subtotalOutput = document.querySelector('#subtotal');
    const discountOutput = document.querySelector('#discount-total');
    const taxOutput = document.querySelector('#tax-total');
    const totalOutput = document.querySelector('#grand-total');
    const changeOutput = document.querySelector('#change-preview');
    const cashInput = document.querySelector('#amount-received');
    const discountInput = document.querySelector('#discount-percent');
    const discountReasonInput = document.querySelector('#discount-reason');
    const form = document.querySelector('#checkout-form');
    const checkoutLines = document.querySelector('#checkout-lines');
    const checkoutButton = document.querySelector('#checkout-button');
    const cart = new Map();
    let totalCents = 0n;
    let searchController;

    function scaled(value, scale) {
        const text = String(value);
        if (!/^\d+(?:\.\d+)?$/.test(text)) return null;
        const [whole, fraction = ''] = text.split('.');
        if (fraction.length > scale) return null;
        return BigInt(whole) * (10n ** BigInt(scale)) + BigInt((fraction + '0'.repeat(scale)).slice(0, scale) || '0');
    }

    function roundedDivide(numerator, denominator) {
        return (numerator + denominator / 2n) / denominator;
    }

    function rupiah(cents) {
        const whole = (cents / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const fraction = (cents % 100n).toString().padStart(2, '0').replace(/0+$/, '');
        return `Rp ${whole}${fraction ? `,${fraction}` : ''}`;
    }

    function lineAmounts(product, quantityMilli) {
        const priceCents = scaled(product.selling_price, 2);
        const taxBasisPoints = scaled(product.tax_rate, 2);
        const discountBasisPoints = discountInput ? (scaled(discountInput.value || '0', 2) ?? 0n) : 0n;
        const lineSubtotal = roundedDivide(priceCents * quantityMilli, 1000n);
        const lineDiscount = roundedDivide(lineSubtotal * discountBasisPoints, 10000n);
        const taxableSubtotal = lineSubtotal - lineDiscount;
        const tax = roundedDivide(taxableSubtotal * taxBasisPoints, 10000n);
        return { lineSubtotal, lineDiscount, tax, total: taxableSubtotal + tax };
    }

    function renderCart() {
        cartLines.replaceChildren();
        let subtotal = 0n;
        let discount = 0n;
        let tax = 0n;
        let quantityCount = 0;
        for (const item of cart.values()) {
            const amount = lineAmounts(item, item.quantityMilli);
            subtotal += amount.lineSubtotal;
            discount += amount.lineDiscount;
            tax += amount.tax;
            quantityCount += 1;

            const row = document.createElement('div');
            row.className = 'grid grid-cols-[minmax(0,1fr)_92px_auto] items-center gap-2 rounded-xl border border-stone-200 p-3';
            const info = document.createElement('div');
            info.className = 'min-w-0';
            const name = document.createElement('p');
            name.className = 'truncate text-sm font-semibold';
            name.textContent = item.name;
            const details = document.createElement('p');
            details.className = 'mt-1 truncate text-xs text-stone-500';
            details.textContent = `${item.sku} · ${rupiah(scaled(item.selling_price, 2))} / ${item.unit_of_measure}`;
            info.append(name, details);

            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.min = '0.001';
            quantity.step = '0.001';
            quantity.max = String(item.stock);
            quantity.value = (Number(item.quantityMilli) / 1000).toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
            quantity.setAttribute('aria-label', `Jumlah ${item.name}`);
            quantity.className = 'w-full rounded-lg border border-stone-300 px-2 py-2 text-right text-sm tabular-nums';
            quantity.addEventListener('change', () => {
                const parsed = scaled(quantity.value, 3);
                const stock = scaled(item.stock, 3);
                if (parsed === null || parsed <= 0n || parsed > stock) {
                    searchStatus.textContent = 'Jumlah harus positif dan tidak melebihi stok yang tersedia.';
                    quantity.value = (Number(item.quantityMilli) / 1000).toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
                    return;
                }
                item.quantityMilli = parsed;
                renderCart();
            });

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'rounded-lg px-2 py-2 text-sm font-semibold text-red-700 hover:bg-red-50';
            remove.textContent = 'Hapus';
            remove.setAttribute('aria-label', `Hapus ${item.name}`);
            remove.addEventListener('click', () => { cart.delete(item.id); renderCart(); });
            row.append(info, quantity, remove);
            cartLines.append(row);
        }

        totalCents = subtotal - discount + tax;
        subtotalOutput.textContent = rupiah(subtotal);
        if (discountOutput) discountOutput.textContent = rupiah(discount);
        taxOutput.textContent = rupiah(tax);
        totalOutput.textContent = rupiah(totalCents);
        cartCount.textContent = `${quantityCount} item`;
        cartEmpty.hidden = quantityCount !== 0;
        checkoutButton.disabled = quantityCount === 0;
        updateChange();
    }

    function updateChange() {
        const received = scaled(cashInput.value || '0', 2);
        changeOutput.textContent = received === null || received < totalCents ? '—' : rupiah(received - totalCents);
    }

    function addProduct(product) {
        const stock = scaled(product.stock, 3);
        if (stock === null || stock <= 0n) {
            searchStatus.textContent = 'Produk ini tidak memiliki stok yang tersedia.';
            return;
        }
        const current = cart.get(product.id);
        if (current) {
            if (current.quantityMilli + 1000n > stock) {
                searchStatus.textContent = 'Jumlah keranjang sudah mencapai stok yang tersedia.';
                return;
            }
            current.quantityMilli += 1000n;
        } else {
            cart.set(product.id, { ...product, quantityMilli: 1000n });
        }
        searchStatus.textContent = `${product.name} ditambahkan ke keranjang.`;
        renderCart();
    }

    async function searchProducts(event) {
        event?.preventDefault();
        const query = searchInput.value.trim();
        if (!query) { results.replaceChildren(); searchStatus.textContent = ''; return; }
        searchController?.abort();
        searchController = new AbortController();
        searchStatus.textContent = 'Mencari…';
        const url = new URL(searchForm.dataset.searchUrl, window.location.href);
        url.searchParams.set('q', query);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: searchController.signal });
            if (!response.ok) throw new Error('Pencarian produk gagal. Coba lagi.');
            const products = await response.json();
            results.replaceChildren();
            if (products.length === 1 && products[0].barcode === query) {
                addProduct(products[0]);
                searchInput.value = '';
                searchInput.focus();
                results.replaceChildren();
                return;
            }
            for (const product of products) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'flex w-full items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3 text-left hover:border-emerald-700 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-emerald-700';
                const text = document.createElement('span');
                text.className = 'min-w-0';
                const title = document.createElement('span');
                title.className = 'block truncate text-sm font-semibold';
                title.textContent = product.name;
                const meta = document.createElement('span');
                meta.className = 'mt-1 block truncate text-xs text-stone-500';
                meta.textContent = `${product.sku}${product.barcode ? ` · ${product.barcode}` : ''} · stok ${product.stock} ${product.unit_of_measure}`;
                const price = document.createElement('span');
                price.className = 'shrink-0 text-sm font-bold tabular-nums';
                price.textContent = rupiah(scaled(product.selling_price, 2));
                text.append(title, meta);
                button.append(text, price);
                button.disabled = scaled(product.stock, 3) <= 0n;
                button.addEventListener('click', () => addProduct(product));
                results.append(button);
            }
            searchStatus.textContent = products.length ? `${products.length} hasil ditemukan.` : 'Produk tidak ditemukan.';
        } catch (error) {
            if (error.name !== 'AbortError') searchStatus.textContent = error.message;
        }
    }

    searchForm.addEventListener('submit', searchProducts);
    cashInput.addEventListener('input', updateChange);
    discountInput?.addEventListener('input', renderCart);
    discountInput?.addEventListener('input', () => { if (discountReasonInput) discountReasonInput.required = scaled(discountInput.value || '0', 2) > 0n; });
    form.addEventListener('submit', (event) => {
        if (cart.size === 0) { event.preventDefault(); return; }
        checkoutLines.replaceChildren();
        let index = 0;
        for (const item of cart.values()) {
            const productId = document.createElement('input');
            productId.type = 'hidden';
            productId.name = `items[${index}][product_id]`;
            productId.value = item.id;
            const quantity = document.createElement('input');
            quantity.type = 'hidden';
            quantity.name = `items[${index}][quantity]`;
            quantity.value = (Number(item.quantityMilli) / 1000).toFixed(3);
            checkoutLines.append(productId, quantity);
            index += 1;
        }
        checkoutButton.disabled = true;
        checkoutButton.textContent = 'Memproses…';
    });
    renderCart();
})();
</script>
@endif
@endsection
