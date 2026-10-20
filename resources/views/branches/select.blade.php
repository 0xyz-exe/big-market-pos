@extends('layouts.guest')

@section('title', 'Pilih cabang')

@section('content')
<main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-5 py-8 sm:px-8 sm:py-12">
    <header class="flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">
            <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span>
            <span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span>
        </a>
        <nav class="flex items-center gap-3">@if(auth()->user()->hasPermission('branches.manage'))<a href="{{ route('admin.branches.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100">Cabang</a>@endif @if(auth()->user()->hasPermission('users.manage'))<a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100">Pengguna</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-700">Keluar</button></form></nav>
    </header>

    <section class="my-auto py-14">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-800">Lokasi operasional</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Pilih cabang</h1>
        <p class="mt-3 max-w-xl text-stone-600">Hai, {{ auth()->user()->name }}. Pilih cabang yang akan Anda kelola. Akses yang tersedia mengikuti penugasan akun Anda.</p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($branches->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-stone-300 bg-white p-8 text-center">
                <h2 class="font-semibold">Belum ada cabang yang dapat diakses</h2>
                <p class="mt-2 text-sm text-stone-600">Hubungi administrator untuk mengaktifkan cabang dan menugaskan akses Anda.</p>
            </div>
        @else
            <form method="POST" action="{{ route('branches.select.store') }}" class="mt-8">
                @csrf
                <fieldset>
                    <legend class="sr-only">Daftar cabang tersedia</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($branches as $branch)
                            <label class="group relative flex cursor-pointer gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:border-emerald-700 hover:shadow-md has-[:checked]:border-emerald-800 has-[:checked]:ring-2 has-[:checked]:ring-emerald-800/15">
                                <input type="radio" name="branch_id" value="{{ $branch->id }}" required class="mt-1 size-4 shrink-0 accent-emerald-800">
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold tracking-wider text-emerald-800">{{ $branch->code }}</span>
                                    <span class="mt-1 block text-lg font-semibold">{{ $branch->name }}</span>
                                    <span class="mt-1 block text-sm text-stone-500">{{ $branch->address ?: 'Alamat belum diatur' }}</span>
                                </span>
                                <span class="absolute right-5 top-5 text-stone-300 transition group-hover:text-emerald-700" aria-hidden="true">→</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <button type="submit" class="mt-6 rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-800">Lanjutkan ke cabang</button>
            </form>
        @endif
    </section>
    <footer class="border-t border-stone-200 pt-5 text-xs text-stone-500">Big Market · Sistem Manajemen</footer>
</main>
@endsection
