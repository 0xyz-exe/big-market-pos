@extends('layouts.guest')

@section('title', 'Pengeluaran cabang')

@section('content')
<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="flex flex-col border-b border-stone-200 bg-white px-5 py-5 lg:min-h-screen lg:border-b-0 lg:border-r lg:px-6">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span><span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span></a>
        <p class="mt-10 text-xs font-bold uppercase tracking-[0.16em] text-stone-400">Menu utama</p>
        <nav class="mt-3 space-y-1" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Ringkasan</a>
            @if(auth()->user()->hasPermission('sales.create'))<a href="{{ route('pos.index') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Kasir POS</a>@endif
            @if(auth()->user()->hasPermission('products.view'))<a href="{{ route('products.index') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Produk &amp; stok</a>@endif
            <a href="{{ route('expenses.index') }}" aria-current="page" class="block rounded-xl bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-900">Pengeluaran</a>
            @if(auth()->user()->hasPermission('expenses.manage'))<a href="{{ route('expense-categories.index') }}" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-stone-600 hover:bg-stone-50">Kategori pengeluaran</a>@endif
        </nav>
        <div class="mt-auto hidden border-t border-stone-200 pt-5 lg:block"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-stone-500">{{ $branch->name }}</p><form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="text-sm font-semibold text-stone-600 hover:text-red-700">Keluar</button></form></div>
    </aside>

    <main class="min-w-0 px-5 py-7 sm:px-8 lg:px-10 lg:py-10">
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-stone-200 pb-6">
            <div><p class="text-sm text-stone-500">{{ $branch->name }} / Keuangan</p><h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Pengeluaran cabang</h1><p class="mt-1 text-sm text-stone-600">Catat biaya operasional; setiap entri menunggu persetujuan sebelum masuk laporan.</p></div>
            @if(auth()->user()->hasPermission('expenses.manage'))<a href="{{ route('expenses.create') }}" class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Catat pengeluaran</a>@endif
        </header>

        @if(session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif
        @if($errors->has('expense'))<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first('expense') }}</div>@endif
        @if($errors->has('decision_note'))<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first('decision_note') }}</div>@endif

        <form method="GET" action="{{ route('expenses.index') }}" class="mt-6 flex max-w-md gap-3">
            <label for="status" class="sr-only">Filter status</label><select id="status" name="status" class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm"><option value="">Semua status</option>@foreach(['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><button class="rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold">Terapkan</button>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            @if($expenses->isEmpty())
                <div class="px-6 py-14 text-center"><p class="font-semibold">Belum ada pengeluaran</p><p class="mt-2 text-sm text-stone-600">Entri cabang ini akan muncul di sini setelah dicatat.</p></div>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-stone-50 text-xs uppercase tracking-wider text-stone-500"><tr><th class="px-5 py-3">Tanggal / keterangan</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Metode</th><th class="px-4 py-3 text-right">Jumlah</th><th class="px-4 py-3">Status / petugas</th><th class="px-5 py-3">Tindakan</th></tr></thead><tbody class="divide-y divide-stone-100">
                    @foreach($expenses as $expense)
                        <tr class="align-top"><td class="px-5 py-4"><span class="block font-semibold">{{ $expense->description }}</span><span class="mt-1 block text-xs text-stone-500">{{ $expense->expense_date->format('d/m/Y') }} · {{ $expense->creator->name }}@if($expense->receipt_reference) · Bukti {{ $expense->receipt_reference }}@endif</span>@if($expense->receipt_path)<a href="{{ route('expenses.receipt', $expense) }}" class="mt-1 inline-block text-xs font-semibold text-emerald-800 hover:underline">Unduh {{ $expense->receipt_original_name }}</a>@endif @if($expense->decision_note)<span class="mt-1 block text-xs text-red-700">Catatan: {{ $expense->decision_note }}</span>@endif</td><td class="px-4 py-4">{{ $expense->category->name }}</td><td class="px-4 py-4">{{ ['cash' => 'Tunai', 'bank_transfer' => 'Transfer', 'card' => 'Kartu', 'other' => 'Lainnya'][$expense->payment_method] ?? $expense->payment_method }}</td><td class="px-4 py-4 text-right tabular-nums">{{ \App\Support\IdrFormatter::format($expense->amount) }}</td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $expense->status === 'approved' ? 'bg-emerald-50 text-emerald-800' : ($expense->status === 'rejected' ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-800') }}">{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$expense->status] }}</span>@if($expense->decider)<span class="mt-2 block text-xs text-stone-500">{{ $expense->decider->name }}</span>@endif</td><td class="px-5 py-4">@if($expense->status === 'pending' && auth()->user()->hasPermission('expenses.approve'))<div class="flex flex-col gap-2"><form method="POST" action="{{ route('expenses.approve', $expense) }}">@csrf<button class="rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white">Setujui</button></form><form method="POST" action="{{ route('expenses.reject', $expense) }}" class="flex flex-col gap-2">@csrf<label for="decision_note_{{ $expense->id }}" class="sr-only">Alasan penolakan</label><input id="decision_note_{{ $expense->id }}" name="decision_note" required minlength="5" maxlength="1000" placeholder="Alasan penolakan" class="rounded-lg border border-stone-300 px-2 py-1.5 text-xs"><button class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-800">Tolak</button></form></div>@else<span class="text-xs text-stone-400">—</span>@endif</td></tr>
                    @endforeach
                </tbody></table></div><div class="border-t border-stone-100 px-5 py-4">{{ $expenses->links() }}</div>
            @endif
        </div>
    </main>
</div>
@endsection
