@extends('layouts.guest')

@section('title', 'Struk '.$sale->transaction_number)

@section('content')
<main class="mx-auto max-w-xl px-4 py-8 print:max-w-none print:px-0 print:py-0">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
        @if (auth()->user()->hasPermission('sales.create'))
            <a href="{{ route('pos.index') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Kembali ke kasir</a>
        @else
            <a href="{{ route('sales.index') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Riwayat penjualan</a>
        @endif
        <button type="button" onclick="window.print()" class="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Cetak struk</button>
    </div>
    <article class="receipt mx-auto bg-white p-5 shadow-sm print:shadow-none" aria-label="Struk penjualan">
        <header class="text-center">
            <p class="text-xl font-black tracking-tight">Big Market</p>
            <p class="mt-1 text-sm">{{ $sale->branch->name }}</p>
            @if ($sale->branch->address)<p class="text-xs">{{ $sale->branch->address }}</p>@endif
            <p class="mt-3 border-t border-dashed border-stone-400 pt-3 text-left text-xs">No. transaksi: {{ $sale->transaction_number }}</p>
            <p class="mt-1 text-left text-xs">Waktu: {{ $sale->completed_at->timezone($sale->branch->timezone)->format('d/m/Y H:i') }}</p>
            <p class="mt-1 text-left text-xs">Kasir: {{ $sale->cashier?->name ?? 'Pengguna dihapus' }}</p>
        </header>
        <div class="mt-4 border-t border-dashed border-stone-400"></div>
        <div class="mt-3 space-y-3">
            @foreach ($sale->items as $item)
                <div class="text-xs">
                    <p class="font-semibold">{{ $item->product_name }}</p>
                    <div class="mt-1 flex justify-between gap-3"><span>{{ $item->quantity }} × {{ \App\Support\IdrFormatter::format($item->unit_price) }}</span><span>{{ \App\Support\IdrFormatter::format($item->subtotal) }}</span></div>
                    @if (bccomp($item->discount_amount, '0.00', 2) > 0)<div class="mt-1 flex justify-between gap-3 text-stone-600"><span>Diskon</span><span>-{{ \App\Support\IdrFormatter::format($item->discount_amount) }}</span></div>@endif
                    @if (bccomp($item->tax_amount, '0.00', 2) > 0)<div class="mt-1 flex justify-between gap-3 text-stone-600"><span>Pajak {{ $item->tax_rate }}%</span><span>{{ \App\Support\IdrFormatter::format($item->tax_amount) }}</span></div>@endif
                </div>
            @endforeach
        </div>
        <div class="mt-4 border-t border-dashed border-stone-400"></div>
        <dl class="mt-3 space-y-1.5 text-xs">
            <div class="flex justify-between gap-3"><dt>Subtotal</dt><dd>{{ \App\Support\IdrFormatter::format($sale->subtotal) }}</dd></div>
            @if (bccomp($sale->discount_amount, '0.00', 2) > 0)<div class="flex justify-between gap-3"><dt>Diskon {{ $sale->discount_percent }}% <span class="text-stone-500">{{ $sale->discount_reason }}</span></dt><dd>-{{ \App\Support\IdrFormatter::format($sale->discount_amount) }}</dd></div>@endif
            <div class="flex justify-between gap-3"><dt>Pajak</dt><dd>{{ \App\Support\IdrFormatter::format($sale->tax_total) }}</dd></div>
            <div class="flex justify-between gap-3 border-t border-stone-300 pt-2 text-sm font-bold"><dt>Total</dt><dd>{{ \App\Support\IdrFormatter::format($sale->total) }}</dd></div>
            <div class="flex justify-between gap-3 pt-1"><dt>Tunai</dt><dd>{{ \App\Support\IdrFormatter::format($sale->amount_received) }}</dd></div>
            <div class="flex justify-between gap-3"><dt>Kembalian</dt><dd>{{ \App\Support\IdrFormatter::format($sale->change_due) }}</dd></div>
        </dl>
        <footer class="mt-5 border-t border-dashed border-stone-400 pt-3 text-center text-xs">Terima kasih telah berbelanja.</footer>
    </article>
</main>
<style>
@media print {
    @page { margin: 3mm; }
    body { background: #fff !important; }
    .receipt { width: 72mm; padding: 3mm !important; font-family: Arial, sans-serif; }
}
@media screen {
    .receipt { width: 80mm; max-width: 100%; }
}
</style>
@endsection
