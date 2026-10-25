<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>DKoper · Keuangan Koperasi</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased dk-body">
        <div class="min-h-screen bg-gray-100 dk-app">
            @include('layouts.navigation')

            <div class="dk-workspace">
                @isset($header)
                    <header class="dk-page-heading">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
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
