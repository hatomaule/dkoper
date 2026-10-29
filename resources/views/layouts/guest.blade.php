<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0b1120">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        @include('layouts.theme-init')

        <title>{{ config('app.name') }} · Platform Keuangan Koperasi</title>

        <!-- Fonts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased dk-guest-body">
        <main class="dk-guest-shell {{ request()->routeIs('login') ? 'dk-login-shell' : '' }}">
            @unless (request()->routeIs('login'))
            <button type="button" class="dk-theme-toggle dk-guest-theme" x-data="dkTheme" @click="toggle()" :aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'" :title="dark ? 'Mode terang' : 'Mode gelap'">
                <svg x-show="!dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.64 5.64l1.42 1.42m9.88 9.88 1.42 1.42m0-12.72-1.42 1.42m-9.88 9.88-1.42 1.42"/><circle cx="12" cy="12" r="4"/></svg>
                <svg x-show="dark" x-cloak viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.8 8.8 0 1 0 20.2 15.1Z"/></svg>
            </button>
            <a href="/" class="dk-guest-brand">
                <img src="{{ asset('logo.svg') }}" class="dk-brand-mark" alt="">
                <span class="dk-brand-name"><span class="dk-brand-title">{{ config('app.name') }}<span>.</span></span><small>COOPERATIVE FINANCE PLATFORM</small></span>
            </a>
            @endunless
            <section class="dk-guest-card dk-login-card">
                @if (request()->routeIs('login'))
                    <header class="dk-login-brand-row">
                        <div class="dk-login-brand" aria-label="FIDES Platform">
                            <span class="dk-login-brand-name">FIDES</span>
                            <span class="dk-login-brand-platform">PLATFORM</span>
                        </div>
                        <button type="button" class="dk-theme-toggle dk-login-theme-toggle" x-data="dkTheme" @click="toggle()" :aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'" :title="dark ? 'Mode terang' : 'Mode gelap'">
                            <svg x-show="!dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.64 5.64l1.42 1.42m9.88 9.88 1.42 1.42m0-12.72-1.42 1.42m-9.88 9.88-1.42-1.42"/><circle cx="12" cy="12" r="4"/></svg>
                            <svg x-show="dark" x-cloak viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.8 8.8 0 1 0 20.2 15.1Z"/></svg>
                        </button>
                    </header>
                @else
                <p class="dk-eyebrow">RUANG KERJA KOPERASI</p>
                <h1>Kelola keuangan dengan lebih tenang.</h1>
                <p class="dk-guest-intro">Masuk untuk melihat ringkasan dan aktivitas koperasi.</p>
                @endif
                {{ $slot }}
            </section>
        </main>
    </body>
</html>
