<div class="dk-heading-tools" x-data="dkTheme">
    @if (Auth::user()->isManager())
        @php
            $adminAccount = \App\Models\User::query()->where('username', 'admin')->where('role', 'kasir')->first();
        @endphp
    @endif
    <div class="dk-topbar-controls">
        <button type="button" class="dk-theme-toggle" @click="toggle()" :aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'" :title="dark ? 'Mode terang' : 'Mode gelap'">
            <svg x-show="!dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.64 5.64l1.42 1.42m9.88 9.88 1.42 1.42m0-12.72-1.42 1.42m-9.88 9.88-1.42 1.42"/><circle cx="12" cy="12" r="4"/></svg>
            <svg x-show="dark" x-cloak viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.8 8.8 0 1 0 20.2 15.1Z"/></svg>
        </button>
        @php
            $recentNotifications = \App\Models\UserNotification::query()->where('user_id', Auth::id())->orderByDesc('id')->limit(5)->get();
            $unreadNotificationCount = \App\Models\UserNotification::query()->where('user_id', Auth::id())->whereNull('read_at')->count();
        @endphp
        <div class="dk-notification-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button type="button" class="dk-notification-trigger" @click="open = !open" :aria-expanded="open.toString()" aria-label="Notifikasi">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <span class="dk-notification-count" data-notification-count="{{ $unreadNotificationCount }}" data-endpoint="{{ route('cooperative.notifications.unread') }}" @if ($unreadNotificationCount === 0) hidden @endif>{{ $unreadNotificationCount }}</span>
            </button>
            <div class="dk-notification-popover" x-show="open" x-transition x-cloak>
                <header><strong>Notifikasi</strong><a href="{{ route('cooperative.notifications') }}">Lihat semua</a></header>
                <div class="dk-notification-items">
                    @forelse ($recentNotifications as $notification)
                        <form method="POST" action="{{ route('cooperative.notifications.open', $notification) }}">
                            @csrf
                            <button type="submit" class="dk-notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                                <span><strong>{{ $notification->title }}</strong><small>{{ $notification->body }}</small><time>{{ $notification->created_at->format('d/m/Y H:i') }}</time></span>
                            </button>
                        </form>
                    @empty
                        <p class="dk-notification-empty">Belum ada notifikasi.</p>
                    @endforelse
                </div>
                @if ($unreadNotificationCount > 0)
                    <form method="POST" action="{{ route('cooperative.notifications.read-all') }}" class="dk-notification-footer">
                        @csrf
                        <button type="submit">Tandai semua dibaca</button>
                    </form>
                @endif
            </div>
        </div>
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="dk-user-trigger dk-profile-trigger" type="button" aria-label="Buka menu akun">
                    <span class="dk-user-avatar">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                    <span class="dk-profile-copy"><strong>{{ Auth::user()->name }}</strong><small>{{ Auth::user()->isManager() ? 'Owner' : (Auth::user()->isCashier() ? 'Admin' : 'Nasabah') }}</small></span>
                    <svg class="dk-profile-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
                </button>
            </x-slot>
            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>
                @if (Auth::user()->isManager() || Auth::user()->isCashier())
                    <button type="button" class="dk-dropdown-action dk-profile-text-action" data-open-dialog="#staff-self-password-dialog">Ganti password saya</button>
                @endif
                @if (Auth::user()->isManager() && $adminAccount)
                    <button type="button" class="dk-dropdown-action dk-profile-text-action" data-open-dialog="#admin-password-dialog">Ganti password Admin</button>
                @endif
                <button type="button" class="dk-dropdown-action dk-profile-text-action dk-profile-logout-action" @click="showLogout = true">Keluar</button>
            </x-slot>
        </x-dropdown>
    </div>

    @if (Auth::user()->isManager() || Auth::user()->isCashier())
        <dialog class="dk-password-native-dialog" id="staff-self-password-dialog" @if ($errors->has('password') && old('_password_form') === 'self') data-open-on-error @endif>
            <form method="POST" action="{{ route('cooperative.staff.update-password', Auth::user()) }}" class="dk-password-form">
                @csrf
                <input type="hidden" name="_password_form" value="self">
                <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                <p class="dk-eyebrow">KEAMANAN AKUN</p>
                <h3>Ganti password saya</h3>
                <label>Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                <label>Ulangi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                <label class="dk-confirmation-check"><input type="checkbox" required> Saya mengonfirmasi perubahan password.</label>
                <button type="submit" class="dk-button-primary">Ganti Password</button>
            </form>
        </dialog>
    @endif

    @if (Auth::user()->isManager() && $adminAccount)
        <dialog class="dk-password-native-dialog" id="admin-password-dialog" @if ($errors->has('password') && old('_password_form') === 'admin') data-open-on-error @endif>
            <form method="POST" action="{{ route('cooperative.staff.update-password', $adminAccount) }}" class="dk-password-form">
                @csrf
                <input type="hidden" name="_password_form" value="admin">
                <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                <p class="dk-eyebrow">KEAMANAN AKUN</p>
                <h3>Ganti password Admin</h3>
                <label>Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                <label>Ulangi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                <label class="dk-confirmation-check"><input type="checkbox" required> Saya mengonfirmasi perubahan password.</label>
                <button type="submit" class="dk-button-primary">Ganti Password</button>
            </form>
        </dialog>
    @endif

    <div class="dk-logout-backdrop" x-show="showLogout" x-transition.opacity x-cloak @click.self="showLogout = false" @keydown.escape.window="showLogout = false">
        <section class="dk-logout-dialog" role="dialog" aria-modal="true" aria-labelledby="logout-title">
            <span class="dk-logout-icon" aria-hidden="true">↗</span>
            <h2 id="logout-title">Keluar dari {{ config('app.name') }}?</h2>
            <p>Sesi akun akan diakhiri pada perangkat ini.</p>
            <div class="dk-logout-actions">
                <button type="button" class="dk-logout-cancel" @click="showLogout = false">Batal</button>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dk-logout-confirm">Keluar</button></form>
            </div>
        </section>
    </div>
</div>
