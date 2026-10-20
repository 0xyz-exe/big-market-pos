@extends('layouts.guest')

@section('title', $isFinancialReport ? 'Laporan keuangan' : 'Laporan penjualan')

@section('content')
<div class="min-h-screen bg-stone-50">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 bg-white px-5 py-4 sm:px-8">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Ringkasan</a>
            <h1 class="mt-1 text-xl font-bold tracking-tight">{{ $isFinancialReport ? 'Laporan keuangan' : 'Laporan penjualan' }}</h1>
            <p class="text-sm text-stone-600">{{ $branch->name }} · {{ $branch->code }} · {{ $branch->timezone }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if (auth()->user()->hasPermission('sales.view'))
                <a href="{{ route('sales.index') }}" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Riwayat</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Keluar</button></form>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <p class="font-semibold">Rentang laporan tidak valid:</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="GET" action="{{ route($reportRoute) }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div><label for="from" class="block text-sm font-semibold">Dari tanggal</label><input id="from" name="from" type="date" value="{{ $from }}" required class="mt-1 rounded-lg border border-stone-300 px-3 py-2 text-sm"></div>
            <div><label for="to" class="block text-sm font-semibold">Sampai tanggal</label><input id="to" name="to" type="date" value="{{ $to }}" required class="mt-1 rounded-lg border border-stone-300 px-3 py-2 text-sm"></div>
            <button class="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Tampilkan laporan</button>
            <p class="w-full text-xs text-stone-500">Rentang maksimal 31 hari berdasarkan zona waktu cabang.</p>
        </form>

        <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Ringkasan penjualan">
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Transaksi selesai</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ number_format($summary['sale_count'], 0, ',', '.') }}</p></article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Subtotal (sebelum pajak)</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($summary['subtotal']) }}</p></article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Diskon penjualan</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($summary['discount_total']) }}</p></article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Pajak tercatat</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($summary['tax_total']) }}</p></article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Total penjualan</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($summary['total']) }}</p></article>
            <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Harga pokok penjualan</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($summary['cost_total']) }}</p></article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-sm text-emerald-900">Laba kotor (subtotal − diskon − HPP)</p><p class="mt-2 text-2xl font-bold tabular-nums text-emerald-950">{{ \App\Support\IdrFormatter::format($summary['gross_profit']) }}</p></article>
        </section>

        @if($isFinancialReport)
            <section class="mt-5 grid gap-3 sm:grid-cols-3" aria-label="Ringkasan pengeluaran disetujui">
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-sm text-amber-900">Pengeluaran disetujui ({{ number_format($financialSummary['approved_expense_count'], 0, ',', '.') }} entri)</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($financialSummary['expense_total']) }}</p></article>
                <article class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Laba kotor</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($financialSummary['gross_profit']) }}</p></article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-sm text-emerald-900">Laba kotor dikurangi pengeluaran disetujui</p><p class="mt-2 text-2xl font-bold tabular-nums">{{ \App\Support\IdrFormatter::format($financialSummary['contribution_after_expenses']) }}</p></article>
            </section>

            <section class="mt-5 grid gap-5 xl:grid-cols-2">
                <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm"><div class="border-b border-stone-200 px-5 py-4"><h2 class="font-bold">Pengeluaran disetujui per hari</h2></div>@if($dailyExpenseTotals === [])<p class="px-5 py-8 text-center text-sm text-stone-600">Tidak ada pengeluaran disetujui dalam rentang ini.</p>@else<div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-stone-50 text-xs uppercase text-stone-500"><tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Entri</th><th class="px-5 py-3 text-right">Jumlah</th></tr></thead><tbody class="divide-y divide-stone-100">@foreach($dailyExpenseTotals as $day)<tr><th class="px-5 py-3 font-semibold">{{ $day['date'] }}</th><td class="px-5 py-3 text-right tabular-nums">{{ number_format($day['count'], 0, ',', '.') }}</td><td class="px-5 py-3 text-right tabular-nums">{{ \App\Support\IdrFormatter::format($day['amount']) }}</td></tr>@endforeach</tbody></table></div>@endif</div>
                <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm"><div class="border-b border-stone-200 px-5 py-4"><h2 class="font-bold">Pengeluaran menurut kategori</h2></div>@if($expenseCategoryTotals === [])<p class="px-5 py-8 text-center text-sm text-stone-600">Belum ada kategori pengeluaran disetujui.</p>@else<div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-stone-50 text-xs uppercase text-stone-500"><tr><th class="px-5 py-3">Kategori</th><th class="px-5 py-3 text-right">Entri</th><th class="px-5 py-3 text-right">Jumlah</th></tr></thead><tbody class="divide-y divide-stone-100">@foreach($expenseCategoryTotals as $category)<tr><th class="px-5 py-3 font-semibold">{{ $category['name'] }}</th><td class="px-5 py-3 text-right tabular-nums">{{ number_format($category['count'], 0, ',', '.') }}</td><td class="px-5 py-3 text-right tabular-nums">{{ \App\Support\IdrFormatter::format($category['amount']) }}</td></tr>@endforeach</tbody></table></div>@endif</div>
            </section>
        @endif

        <section class="mt-5 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm" aria-label="Penjualan harian">
            <div class="border-b border-stone-200 px-5 py-4"><h2 class="font-bold">Rincian harian</h2></div>
            @if ($dailyTotals === [])
                <p class="px-5 py-10 text-center text-sm text-stone-600">Tidak ada transaksi pada rentang tanggal ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
                        <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500"><tr><th scope="col" class="px-5 py-3 font-semibold">Tanggal</th><th scope="col" class="px-5 py-3 text-right font-semibold">Transaksi</th><th scope="col" class="px-5 py-3 text-right font-semibold">Subtotal</th><th scope="col" class="px-5 py-3 text-right font-semibold">Diskon</th><th scope="col" class="px-5 py-3 text-right font-semibold">Pajak</th><th scope="col" class="px-5 py-3 text-right font-semibold">Total</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($dailyTotals as $day)
                                <tr><th scope="row" class="whitespace-nowrap px-5 py-4 font-semibold">{{ $day['date'] }}</th><td class="px-5 py-4 text-right tabular-nums">{{ number_format($day['sale_count'], 0, ',', '.') }}</td><td class="px-5 py-4 text-right tabular-nums">{{ \App\Support\IdrFormatter::format($day['subtotal']) }}</td><td class="px-5 py-4 text-right tabular-nums">{{ \App\Support\IdrFormatter::format($day['discount_total']) }}</td><td class="px-5 py-4 text-right tabular-nums">{{ \App\Support\IdrFormatter::format($day['tax_total']) }}</td><td class="px-5 py-4 text-right font-semibold tabular-nums">{{ \App\Support\IdrFormatter::format($day['total']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        <p class="mt-4 text-xs leading-5 text-stone-500">@if($isFinancialReport)Laporan ini mengurangi laba kotor (setelah diskon tercatat) dengan pengeluaran yang disetujui saja. Ini bukan laba bersih akuntansi dan belum memperhitungkan pengembalian, pembelian, pajak biaya, pembayaran non-tunai, atau beban lain.@else Laporan penjualan merangkum transaksi selesai, diskon tercatat, dan HPP tersimpan. Untuk ringkasan pengeluaran yang disetujui, gunakan laporan keuangan.@endif</p>
    </main>
</div>
@endsection
