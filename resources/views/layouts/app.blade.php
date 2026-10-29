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

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class(['font-sans antialiased dk-body', 'dk-owner-theme' => auth()->user()?->isManager() || auth()->user()?->isCashier() || auth()->user()?->isBorrower()])>
        <div class="min-h-screen bg-gray-100 dk-app">
            @include('layouts.navigation')

            <div class="dk-workspace">
                @isset($header)
                    <header class="dk-page-heading {{ request()->routeIs('dashboard') ? 'dk-dashboard-heading' : '' }}">
                        <div class="dk-heading-inner mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            <div class="dk-heading-layout">{{ $header }}</div>
                            @include('layouts.topbar-controls')
                        </div>
                    </header>
                @endisset

                <main class="dk-main">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
