@extends('layouts.guest')

@section('title', 'Ganti kata sandi')

@section('content')
<main class="flex min-h-screen items-center justify-center bg-stone-50 px-5 py-10">
    <section class="w-full max-w-lg rounded-3xl border border-stone-200 bg-white p-7 shadow-sm sm:p-9">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-xl bg-emerald-800 text-lg font-black text-white">B</span><span class="font-bold tracking-tight">Big Market <span class="font-medium text-stone-500">POS</span></span></a>
        <h1 class="mt-8 text-2xl font-bold tracking-tight">Ganti kata sandi</h1>
        <p class="mt-2 text-sm leading-6 text-stone-600">Gunakan kata sandi baru minimal 12 karakter. Kata sandi tidak dikirim melalui email atau ditampilkan kembali.</p>
        @if(session('status'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-4">@csrf @method('PUT')
            <div><label for="current_password" class="mb-1.5 block text-sm font-semibold">Kata sandi saat ini</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"></div>
            <div><label for="password" class="mb-1.5 block text-sm font-semibold">Kata sandi baru</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"></div>
            <div><label for="password_confirmation" class="mb-1.5 block text-sm font-semibold">Ulangi kata sandi baru</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="w-full rounded-xl border border-stone-300 px-3 py-2.5"></div>
            <button class="w-full rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900">Perbarui kata sandi</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="w-full rounded-xl border border-stone-300 px-4 py-2.5 text-sm font-semibold text-stone-700">Keluar</button></form>
    </section>
</main>
@endsection
