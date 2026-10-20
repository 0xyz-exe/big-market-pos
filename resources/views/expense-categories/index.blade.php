@extends('layouts.guest')

@section('title', 'Kategori pengeluaran')

@section('content')
<main class="mx-auto min-h-screen max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
    <header class="flex flex-wrap items-center justify-between gap-4"><a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span><span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span></a><a href="{{ route('expenses.index') }}" class="text-sm font-semibold text-stone-600 hover:text-emerald-800">Kembali ke pengeluaran</a></header>
    <section class="mt-10"><p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-800">{{ $branch->name }} · Keuangan</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Kategori pengeluaran</h1><p class="mt-2 text-sm text-stone-600">Kategori yang telah dipakai tetap tersimpan; nonaktifkan agar tidak muncul pada entri baru.</p>
        @if(session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('expense-categories.store') }}" class="mt-6 flex flex-col gap-3 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:flex-row">@csrf<label class="sr-only" for="new-expense-category">Nama kategori baru</label><input id="new-expense-category" name="name" required maxlength="100" placeholder="Nama kategori" class="min-w-0 flex-1 rounded-xl border border-stone-300 px-3 py-2.5"><button class="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white">Tambah kategori</button></form>
        <section class="mt-5 space-y-3" aria-label="Daftar kategori pengeluaran">
            @forelse($categories as $category)
                <form method="POST" action="{{ route('expense-categories.update', $category) }}" class="grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_auto_auto]">@csrf @method('PUT')<label class="sr-only" for="expense-category-name-{{ $category->id }}">Nama kategori</label><input id="expense-category-name-{{ $category->id }}" name="name" value="{{ $category->name }}" required maxlength="100" class="rounded-xl border border-stone-300 px-3 py-2.5"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="size-4 rounded border-stone-300"> Aktif</label><button class="rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-semibold">Simpan</button></form>
            @empty<p class="rounded-xl border border-stone-200 bg-white p-6 text-center text-sm text-stone-600">Belum ada kategori pengeluaran. Jalankan `php artisan db:seed` untuk kategori awal.</p>@endforelse
            <div>{{ $categories->links() }}</div>
        </section>
    </section>
</main>
@endsection
