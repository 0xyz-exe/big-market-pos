@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<main class="grid min-h-screen lg:grid-cols-2">
    <section class="flex items-center justify-center px-6 py-12 sm:px-10">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">
                <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-800 text-xl font-black text-white">B</span>
                <span class="text-lg font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span>
            </a>
            <div class="mt-12">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-800">Sistem operasional toko</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Selamat datang kembali</h1>
                <p class="mt-3 text-stone-600">Masuk dengan akun staf Big Market untuk melanjutkan.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-semibold">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="mt-2 block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10 @error('email') border-red-600 @enderror">
                    @error('email')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold">Kata sandi</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        class="mt-2 block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-base shadow-sm outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10">
                </div>
                <label class="flex items-center gap-3 text-sm text-stone-700">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-stone-300 text-emerald-800 focus:ring-emerald-700">
                    Ingat saya di perangkat ini
                </label>
                <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-emerald-800 px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-800">
                    Masuk ke sistem
                </button>
            </form>
            <p class="mt-8 text-xs leading-5 text-stone-500">Akses hanya untuk staf yang terdaftar. Hubungi administrator cabang jika akun Anda belum aktif.</p>
        </div>
    </section>
    <aside class="relative hidden overflow-hidden bg-emerald-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="absolute -right-24 -top-20 size-96 rounded-full border border-white/10"></div>
        <div class="absolute -right-6 top-12 size-64 rounded-full border border-white/10"></div>
        <div class="relative max-w-lg">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 px-3 py-1.5 text-xs font-semibold tracking-wide text-emerald-100"><span class="size-2 rounded-full bg-lime-300"></span>OPERASIONAL TERPADU</span>
            <h2 class="mt-8 text-4xl font-semibold leading-tight tracking-tight">Semua cabang.<br>Dalam satu kendali.</h2>
            <p class="mt-5 max-w-md leading-7 text-emerald-100/75">Kelola operasional toko dengan data yang terstruktur, akses berbasis peran, dan catatan transaksi yang dapat ditelusuri.</p>
        </div>
        <div class="relative flex items-center justify-between border-t border-white/15 pt-5 text-xs text-emerald-100/60">
            <span>Big Market · Sistem Manajemen</span><span>IDR · Asia/Jakarta</span>
        </div>
    </aside>
</main>
@endsection
