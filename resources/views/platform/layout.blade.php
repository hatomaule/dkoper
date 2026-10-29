<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>FIDES · @yield('page-title', 'Platform')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileMenuOpen: false }" x-effect="document.body.style.overflow = mobileMenuOpen ? 'hidden' : ''" @keydown.escape.window="mobileMenuOpen = false" class="min-h-screen bg-[#080d1b] font-sans text-slate-200 antialiased" data-platform-auto-refresh="60000">
    @php($currentCooperativeId = (int) request()->query('cooperative', $platformCooperatives->first()?->id ?? 0))
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-800 bg-[#0e1526] md:flex">
        <a href="{{ route('platform.dashboard') }}" class="flex h-16 items-center gap-3 border-b border-slate-800 px-6">
            <span class="text-xl font-black italic tracking-tight text-orange-500">FIDES</span>
            <span class="rounded bg-orange-500/10 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-orange-400">Platform</span>
        </a>
        <div class="px-5 pt-7 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Workspace</div>
        <nav class="mt-3 space-y-1 px-3">
            <a href="{{ route('platform.dashboard') }}" @class([
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                'bg-slate-800 text-white' => request()->routeIs('platform.dashboard', 'platform.cooperatives.show'),
                'text-slate-400 hover:bg-slate-800/70 hover:text-white' => ! request()->routeIs('platform.dashboard', 'platform.cooperatives.show'),
            ])>
                <span class="w-5 text-center text-orange-400">▦</span> Dashboard
            </a>
            <a href="{{ route('platform.cooperatives') }}" @class([
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                'bg-slate-800 text-white' => request()->routeIs('platform.cooperatives*'),
                'text-slate-400 hover:bg-slate-800/70 hover:text-white' => ! request()->routeIs('platform.cooperatives*'),
            ])>
                <span class="w-5 text-center text-orange-400">◈</span> Manajemen Koperasi
            </a>
            <a href="{{ route('platform.accounts') }}" @class([
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                'bg-slate-800 text-white' => request()->routeIs('platform.accounts*'),
                'text-slate-400 hover:bg-slate-800/70 hover:text-white' => ! request()->routeIs('platform.accounts*'),
            ])>
                <span class="w-5 text-center text-orange-400">♙</span> Manajemen akun
            </a>
            <div class="ml-8 border-l border-slate-800 py-1 pl-3">
                @forelse ($platformCooperatives as $platformCooperative)
                    <a href="{{ route('platform.accounts', ['cooperative' => $platformCooperative->id]) }}"
                       @class([
                           'mb-1 flex items-center justify-between gap-2 rounded-md px-3 py-2 text-xs transition',
                           'bg-slate-800/80 text-white' => $currentCooperativeId === $platformCooperative->id && request()->routeIs('platform.accounts'),
                           'text-slate-500 hover:bg-slate-800/60 hover:text-slate-200' => ! ($currentCooperativeId === $platformCooperative->id && request()->routeIs('platform.accounts')),
                       ])>
                        <span class="truncate">{{ $platformCooperative->name }}</span>
                        <span @class(['h-1.5 w-1.5 shrink-0 rounded-full', 'bg-emerald-400' => $platformCooperative->status === 'active', 'bg-slate-600' => $platformCooperative->status !== 'active'])></span>
                    </a>
                @empty
                    <p class="px-3 py-2 text-[11px] text-slate-600">Belum ada koperasi</p>
                @endforelse
            </div>
        </nav>
    </aside>

    <div class="min-h-screen md:pl-64">
        <header class="sticky top-0 z-20 border-b border-slate-800 bg-[#0b1120]/95 backdrop-blur">
            <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">FIDES Platform</p>
                    <h1 class="mt-0.5 text-lg font-bold text-white">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="hidden items-center gap-2 text-xs text-slate-500 sm:flex">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        Pemantauan sistem
                    </div>
                    <button type="button" @click="mobileMenuOpen = true" :aria-expanded="mobileMenuOpen.toString()" aria-controls="platform-mobile-navigation" aria-label="Buka menu navigasi" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-700 text-slate-300 transition hover:border-orange-400/60 hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400 md:hidden">
                        <svg x-show="!mobileMenuOpen" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <svg x-show="mobileMenuOpen" x-cloak viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-label="Buka menu profil Superuser" class="flex h-10 w-10 items-center justify-center rounded-full border border-orange-400/40 bg-orange-500/15 text-sm font-bold text-orange-200 transition hover:border-orange-300 hover:bg-orange-500/25 focus:outline-none focus:ring-2 focus:ring-orange-400">
                            {{ str(auth('platform')->user()->name)->substr(0, 1)->upper() }}
                        </button>
                        <div x-cloak x-show="open" x-transition.origin.top.right @click.stop class="absolute right-0 z-50 mt-2 w-64 rounded-xl border border-slate-700 bg-[#101829] p-4 shadow-2xl shadow-black/50">
                            <div class="border-b border-slate-800 pb-3">
                                <p class="font-semibold text-white">{{ auth('platform')->user()->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">Superuser FIDES</p>
                            </div>
                            <button type="button" data-open-dialog="platform-password-dialog" @click="open = false" class="mt-3 block w-full text-left text-xs font-semibold text-slate-300 transition hover:text-orange-300">Ganti password</button>
                            <form method="POST" action="{{ route('platform.logout') }}" class="mt-3 border-t border-slate-800 pt-3">
                                @csrf
                                <button class="block w-full text-left text-xs font-semibold text-slate-300 transition hover:text-orange-300">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div x-cloak x-show="mobileMenuOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/65 backdrop-blur-[2px] md:hidden" aria-hidden="true">
            <button type="button" @click="mobileMenuOpen = false" class="h-full w-full cursor-default" tabindex="-1" aria-label="Tutup menu navigasi"></button>
        </div>
        <aside
            id="platform-mobile-navigation"
            x-cloak
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full opacity-90"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-full opacity-90"
            class="fixed inset-y-0 right-0 z-50 flex w-[min(21rem,88vw)] flex-col border-l border-slate-700 bg-[#0e1526] shadow-2xl shadow-black/50 md:hidden"
            aria-label="Navigasi Platform"
        >
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-800 px-5">
                <div class="flex items-center gap-2">
                    <span class="text-lg font-black italic tracking-tight text-orange-500">FIDES</span>
                    <span class="rounded bg-orange-500/10 px-2 py-1 text-[9px] font-bold uppercase tracking-widest text-orange-400">Platform</span>
                </div>
                <button type="button" @click="mobileMenuOpen = false" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-orange-400" aria-label="Tutup menu navigasi">
                    <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <nav class="min-h-0 flex-1 overflow-y-auto p-4" aria-label="Menu utama">
                <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Workspace</p>
                <a href="{{ route('platform.dashboard') }}" @click="mobileMenuOpen = false" @class([
                    'mb-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                    'bg-slate-800 text-white' => request()->routeIs('platform.dashboard', 'platform.cooperatives.show'),
                    'text-slate-400 hover:bg-slate-800/70 hover:text-white' => ! request()->routeIs('platform.dashboard', 'platform.cooperatives.show'),
                ])><span class="w-5 text-center text-orange-400">▦</span>Dashboard</a>
                <a href="{{ route('platform.cooperatives') }}" @click="mobileMenuOpen = false" @class([
                    'mb-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                    'bg-slate-800 text-white' => request()->routeIs('platform.cooperatives') && ! request()->routeIs('platform.cooperatives.show'),
                    'text-slate-400 hover:bg-slate-800/70 hover:text-white' => ! request()->routeIs('platform.cooperatives') || request()->routeIs('platform.cooperatives.show'),
                ])><span class="w-5 text-center text-orange-400">◈</span>Manajemen Koperasi</a>
                <a href="{{ route('platform.accounts') }}" @click="mobileMenuOpen = false" @class([
                    'mb-1 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                    'bg-slate-800 text-white' => request()->routeIs('platform.accounts*'),
                    'text-slate-400 hover:bg-slate-800/70 hover:text-white' => ! request()->routeIs('platform.accounts*'),
                ])><span class="w-5 text-center text-orange-400">♙</span>Manajemen akun</a>
                @if ($platformCooperatives->isNotEmpty())
                    <div class="mb-2 ml-8 mt-2 border-l border-slate-800 py-1 pl-3">
                        @foreach ($platformCooperatives as $platformCooperative)
                            <a href="{{ route('platform.accounts', ['cooperative' => $platformCooperative->id]) }}" @click="mobileMenuOpen = false" @class([
                                'mb-1 flex min-h-9 items-center justify-between gap-2 rounded-md px-3 py-2 text-xs transition',
                                'bg-slate-800/80 text-white' => $currentCooperativeId === $platformCooperative->id && request()->routeIs('platform.accounts'),
                                'text-slate-500 hover:bg-slate-800/60 hover:text-slate-200' => ! ($currentCooperativeId === $platformCooperative->id && request()->routeIs('platform.accounts')),
                            ])>
                                <span class="truncate">{{ $platformCooperative->name }}</span>
                                <span @class(['h-1.5 w-1.5 shrink-0 rounded-full', 'bg-emerald-400' => $platformCooperative->status === 'active', 'bg-slate-600' => $platformCooperative->status !== 'active'])></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </nav>
            <form method="POST" action="{{ route('platform.logout') }}" class="shrink-0 border-t border-slate-800 p-4">
                @csrf
                <button class="flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white">Keluar</button>
            </form>
        </aside>

        <dialog id="platform-password-dialog" @if ($errors->has('current_password') || $errors->has('password')) data-open-on-error @endif class="w-[min(26rem,calc(100%-2rem))] rounded-xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
            <form method="POST" action="{{ route('platform.profile.password') }}" class="space-y-4 p-5">
                @csrf
                <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">Keamanan akun</p>
                <h2 class="text-lg font-bold text-white">Ganti password Superuser</h2>
                <label class="block text-xs font-medium text-slate-400">Password saat ini
                    <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                </label>
                <label class="block text-xs font-medium text-slate-400">Password baru
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                </label>
                <label class="block text-xs font-medium text-slate-400">Ulangi password baru
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-1 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300">Batal</button>
                    <button type="submit" class="rounded-md bg-orange-500 px-3 py-2 text-xs font-bold text-slate-950 transition hover:bg-orange-400">Simpan password</button>
                </div>
            </form>
        </dialog>

        <main class="mx-auto max-w-[1500px] space-y-6 px-4 pb-4 pt-0 sm:px-6 sm:pb-6 sm:pt-0 lg:px-8 lg:pb-8 lg:pt-0">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <p class="font-semibold">Periksa kembali data yang dimasukkan.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
