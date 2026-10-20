@extends('layouts.guest')

@section('title', 'Riwayat penjualan')

@section('content')
<div class="min-h-screen bg-stone-50">
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200 bg-white px-5 py-4 sm:px-8">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Ringkasan</a>
            <h1 class="mt-1 text-xl font-bold tracking-tight">Riwayat penjualan</h1>
            <p class="text-sm text-stone-600">{{ $branch->name }} · {{ $branch->code }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if (auth()->user()->hasPermission('sales.create'))
                <a href="{{ route('pos.index') }}" class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Buka kasir</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Keluar</button></form>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('sales.index') }}" class="mb-5 flex max-w-xl gap-2" role="search">
            <label class="sr-only" for="sale-search">Cari nomor transaksi</label>
            <input id="sale-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Cari nomor transaksi" class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm focus:border-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-700/10">
            <button class="rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm font-semibold text-stone-700 hover:bg-stone-50">Cari</button>
        </form>

        <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm" aria-label="Daftar transaksi">
            @if ($sales->isEmpty())
                <p class="px-5 py-12 text-center text-sm text-stone-600">{{ $search !== '' ? 'Tidak ada transaksi yang cocok.' : 'Belum ada transaksi pada cabang ini.' }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
                        <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr><th scope="col" class="px-5 py-3 font-semibold">Nomor transaksi</th><th scope="col" class="px-5 py-3 font-semibold">Waktu</th><th scope="col" class="px-5 py-3 font-semibold">Kasir</th><th scope="col" class="px-5 py-3 text-right font-semibold">Total</th><th scope="col" class="px-5 py-3 text-right font-semibold">Struk</th></tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($sales as $sale)
                                <tr class="hover:bg-stone-50">
                                    <th scope="row" class="whitespace-nowrap px-5 py-4 font-semibold text-stone-900">{{ $sale->transaction_number }}</th>
                                    <td class="whitespace-nowrap px-5 py-4 text-stone-600">{{ $sale->completed_at?->timezone($branch->timezone)->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-stone-600">{{ $sale->cashier?->name ?? 'Pengguna dihapus' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold tabular-nums">{{ \App\Support\IdrFormatter::format((string) $sale->total) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('sales.receipt', $sale) }}" class="font-semibold text-emerald-800 hover:underline">Lihat struk</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-stone-200 px-5 py-4">{{ $sales->links() }}</div>
            @endif
        </section>
    </main>
</div>
@endsection
