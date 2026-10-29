<nav x-data="{ open: false }" class="dk-nav">
    <div class="dk-sidebar">
        <a href="{{ route('dashboard') }}" class="dk-brand" aria-label="{{ config('app.name') }}, Dashboard">
            <img src="{{ asset('logo.svg') }}" class="dk-brand-mark" alt="">
            <span class="dk-brand-name"><span class="dk-brand-title">{{ config('app.name') }}<span>.</span></span><small>COOPERATIVE FINANCE</small></span>
        </a>

        <div class="dk-sidebar-caption">MENU UTAMA</div>
        <div class="dk-nav-links">
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                <span>Dashboard</span>
            </x-nav-link>
            @if (auth()->user()->isManager() || auth()->user()->isCashier())
                <x-nav-link :href="route('cooperative.borrowers')" :active="request()->routeIs('cooperative.borrowers')">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M3 20v-1.5A4.5 4.5 0 0 1 7.5 14h3a4.5 4.5 0 0 1 4.5 4.5V20M16 5.2a3.5 3.5 0 0 1 0 6.6M17 14h.5a3.5 3.5 0 0 1 3.5 3.5V20"/></svg>
                    <span>Nasabah</span>
                </x-nav-link>
                <x-nav-link :href="route('cooperative.loans')" :active="request()->routeIs('cooperative.loans')">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.5h16v13H4zM7 6.5V4h10v2.5M8 11h8M8 15h5"/></svg>
                    <span>Pinjaman</span>
                </x-nav-link>
                <x-nav-link :href="route('cooperative.payments')" :active="request()->routeIs('cooperative.payments')">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/></svg>
                    <span>Pembayaran</span>
                </x-nav-link>
                @if (auth()->user()->isManager())
                    <div class="dk-sidebar-caption dk-sidebar-caption-spaced">PENGELOLAAN</div>
                    <x-nav-link :href="route('cooperative.approvals')" :active="request()->routeIs('cooperative.approvals')">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 20 7v5.2c0 4.4-3.1 7.1-8 8.8-4.9-1.7-8-4.4-8-8.8V7z"/><path d="m8.5 12 2.2 2.2 4.8-4.8"/></svg>
                        <span>Persetujuan</span>
                    </x-nav-link>
                    <x-nav-link :href="route('cooperative.finances')" :active="request()->routeIs('cooperative.finances')">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5h16M6.5 16V10M12 16V5M17.5 16v-4"/></svg>
                        <span>Keuangan</span>
                    </x-nav-link>
                @endif
                @if (auth()->user()->isManager() || auth()->user()->isCashier())
                    <x-nav-link :href="route('cooperative.profits')" :active="request()->routeIs('cooperative.profits')">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M15 8.5c-.7-.7-1.6-1-2.8-1-1.4 0-2.4.7-2.4 1.8 0 2.8 5.3 1.1 5.3 4.1 0 1.2-1.1 2.1-2.8 2.1-1.2 0-2.3-.4-3.2-1.2M12 5.5v13"/></svg>
                        <span>Bonus</span>
                    </x-nav-link>
                    <x-nav-link :href="route('cooperative.activity-logs')" :active="request()->routeIs('cooperative.activity-logs')">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5.5h14v14H5zM8 9h8M8 12.5h8M8 16h5M8 3v5M16 3v5"/></svg>
                        <span>Aktivitas</span>
                    </x-nav-link>
                @endif
            @endif
        </div>

    </div>

    <div class="dk-mobile-bar">
        <a href="{{ route('dashboard') }}" class="dk-brand" aria-label="{{ config('app.name') }}, Dashboard">
            <img src="{{ asset('logo.svg') }}" class="dk-brand-mark" alt="">
            <span class="dk-brand-name"><span class="dk-brand-title">{{ config('app.name') }}<span>.</span></span><small>COOPERATIVE FINANCE</small></span>
        </a>
        <button class="dk-menu-toggle" type="button" @click="open = ! open" :aria-expanded="open.toString()" :aria-label="open ? 'Tutup navigasi' : 'Buka navigasi'" aria-controls="mobile-navigation-menu">
            <svg x-show="!open" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            <svg x-show="open" x-cloak viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
    </div>

    <div class="dk-mobile-overlay" x-show="open" x-transition.opacity x-cloak @click="open = false"></div>
    <div
        id="mobile-navigation-menu"
        class="dk-mobile-menu"
        x-show="open"
        x-transition:enter="dk-mobile-drawer-enter"
        x-transition:enter-start="dk-mobile-drawer-enter-start"
        x-transition:enter-end="dk-mobile-drawer-enter-end"
        x-transition:leave="dk-mobile-drawer-leave"
        x-transition:leave-start="dk-mobile-drawer-leave-start"
        x-transition:leave-end="dk-mobile-drawer-leave-end"
        x-cloak
        @keydown.escape.window="open = false"
        @click="if ($event.target.closest('a')) open = false"
    >
        <div class="dk-mobile-caption">MENU</div>
        <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-responsive-nav-link>
        @if (auth()->user()->isManager() || auth()->user()->isCashier())
            <x-responsive-nav-link :href="route('cooperative.borrowers')" :active="request()->routeIs('cooperative.borrowers')">Nasabah</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('cooperative.loans')" :active="request()->routeIs('cooperative.loans')">Pinjaman</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('cooperative.payments')" :active="request()->routeIs('cooperative.payments')">Pembayaran</x-responsive-nav-link>
            @if (auth()->user()->isManager())
                <div class="dk-mobile-caption dk-mobile-caption-spaced">PENGELOLAAN</div>
                <x-responsive-nav-link :href="route('cooperative.approvals')" :active="request()->routeIs('cooperative.approvals')">Persetujuan</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('cooperative.finances')" :active="request()->routeIs('cooperative.finances')">Keuangan</x-responsive-nav-link>
            @endif
            @if (auth()->user()->isManager() || auth()->user()->isCashier())
                <x-responsive-nav-link :href="route('cooperative.profits')" :active="request()->routeIs('cooperative.profits')">Bonus</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('cooperative.activity-logs')" :active="request()->routeIs('cooperative.activity-logs')">Aktivitas</x-responsive-nav-link>
            @endif
        @endif
    </div>
</nav>
