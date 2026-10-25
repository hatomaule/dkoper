<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>DKoper · Keuangan Koperasi</title>

        <!-- Fonts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased dk-guest-body">
        <main class="dk-guest-shell">
            <a href="/" class="dk-guest-brand">
                <span class="dk-brand-mark">D</span>
                <span class="dk-brand-name"><span class="dk-brand-title">DKoper<span>.</span></span><small>KEUANGAN KOPERASI</small></span>
            </a>
            <section class="dk-guest-card">
                <p class="dk-eyebrow">RUANG KERJA KOPERASI</p>
                <h1>Kelola keuangan dengan lebih tenang.</h1>
                <p class="dk-guest-intro">Masuk untuk melihat ringkasan dan aktivitas koperasi.</p>
                {{ $slot }}
            </section>
        </main>
    </body>
</html>
