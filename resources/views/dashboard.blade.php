@extends('layouts.guest')

@section('title', 'Ringkasan cabang')

@section('content')
<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="flex flex-col border-b border-stone-200 bg-white px-5 py-5 lg:min-h-screen lg:border-b-0 lg:border-r lg:px-6">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">
            <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span>
            <span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span>
        </a>
        <p class="mt-10 text-xs font-bold uppercase tracking-[0.16em] text-stone-400">Menu utama</p>
        <nav class="mt-3 space-y-1" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" aria-current="page" class="flex items-center gap-3 rounded-xl bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-900">Ringkasan</a>
            @if (auth()->user()->hasPermission('sales.create'))
                <a href="{{ route('pos.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Kasir POS</a>
            @else
                <span class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-stone-400" aria-disabled="true">Kasir POS</span>
            @endif
            @if (auth()->user()->hasPermission('products.view'))
                <a href="{{ route('products.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Produk &amp; stok</a>
            @else
                <span class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-stone-400" aria-disabled="true">Produk &amp; stok</span>
            @endif
            @if(auth()->user()->hasPermission('expenses.manage'))<a href="{{ route('expenses.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Pengeluaran</a>@endif
            @if(auth()->user()->hasPermission('products.manage'))<a href="{{ route('categories.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Kategori produk</a>@endif
            @if(auth()->user()->hasPermission('expenses.manage'))<a href="{{ route('expense-categories.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Kategori pengeluaran</a>@endif
            @if(auth()->user()->hasPermission('branches.manage'))<a href="{{ route('admin.branches.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Administrasi cabang</a>@endif
            @if(auth()->user()->hasPermission('users.manage'))<a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Pengguna &amp; peran</a>@endif
            @if(auth()->user()->hasPermission('shifts.manage'))<a href="{{ route('shifts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Shift kasir</a>@endif
            @if (auth()->user()->hasPermission('sales.view'))
                <a href="{{ route('sales.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Riwayat penjualan</a>
            @endif
            @if (auth()->user()->hasPermission('reports.sales'))
                <a href="{{ route('reports.sales') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Laporan penjualan</a>
            @endif
            @if (auth()->user()->hasPermission('reports.financial'))
                <a href="{{ route('reports.financial') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Laporan keuangan</a>
            @endif
        </nav>
        <div class="mt-auto hidden border-t border-stone-200 pt-5 lg:block">
            <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-stone-500">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-sm font-semibold text-stone-600 hover:text-red-700">Keluar</button></form>
        </div>
    </aside>

    <main class="px-5 py-7 sm:px-8 lg:px-10 lg:py-10">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-200 pb-6">
            <div>
                <p class="text-sm text-stone-500">Operasional / {{ $branch->name }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Ringkasan cabang</h1>
                <p class="mt-1 text-sm text-stone-600">{{ $branch->code }} · {{ $branch->timezone }}</p>
            </div>
            <a href="{{ route('branches.select') }}" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-emerald-700">Ganti cabang</a>
        </header>

        <section class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6" aria-labelledby="milestone-heading">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-800">Fondasi aplikasi</p>
            <h2 id="milestone-heading" class="mt-2 text-lg font-bold text-stone-900">Ruang kerja cabang siap</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-700">Autentikasi, pemilihan cabang, katalog, penyesuaian stok, checkout tunai, dan laporan penjualan tersedia untuk staf berizin. Checkout memeriksa harga, pajak, kembalian, dan saldo stok di server; penerimaan barang, shift kasir, pengembalian, dan laporan keuangan menyeluruh belum tersedia.</p>
        </section>

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Modul sistem">
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Tersedia</p><h2 class="mt-3 font-semibold">Katalog produk</h2><p class="mt-2 text-sm leading-6 text-stone-600">Tambah dan cari produk berdasarkan nama, SKU, atau barcode dengan harga serta saldo khusus cabang.</p>@if(auth()->user()->hasPermission('products.view'))<a href="{{ route('products.index') }}" class="mt-4 inline-flex text-sm font-semibold text-emerald-800 hover:underline">Buka katalog →</a>@endif</article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Integritas stok</p><h2 class="mt-3 font-semibold">Persediaan terlacak</h2><p class="mt-2 text-sm leading-6 text-stone-600">Saldo cabang dan mutasi stok yang tertaut ke sumbernya.</p></article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Transaksi tunai</p><h2 class="mt-3 font-semibold">Kasir POS</h2><p class="mt-2 text-sm leading-6 text-stone-600">Checkout tunai dengan harga dan stok server-side, nomor transaksi unik, dan struk dari data tersimpan.</p>@if(auth()->user()->hasPermission('sales.create'))<a href="{{ route('pos.index') }}" class="mt-4 inline-flex text-sm font-semibold text-emerald-800 hover:underline">Buka kasir →</a>@endif</article>
        </section>
    </main>
</div>
@endsection
