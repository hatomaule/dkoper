<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Notifikasi</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 px-4 py-8">
        <div class="mx-auto max-w-5xl">
            <section class="dk-data-panel" data-table-tools>
                <div class="dk-table-toolbar">
                    <p class="dk-notification-summary">{{ $unreadCount }} belum dibaca</p>
                    <label>Filter <input type="search" placeholder="Cari notifikasi..." data-table-search></label>
                    <label>Tampilkan
                        <select data-page-size>
                            <option value="auto" @selected(request()->query('per_page') !== 'all')>Otomatis</option>
                            <option value="all" @selected(request()->query('per_page') === 'all')>Semua</option>
                        </select>
                    </label>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('cooperative.notifications.read-all') }}">@csrf<button type="submit" class="dk-inline-action">Tandai semua dibaca</button></form>
                    @endif
                </div>
                <div class="dk-notification-list">
                    @forelse ($notifications as $notification)
                        <article class="dk-notification-row {{ $notification->read_at ? 'is-read' : 'is-unread' }}" data-table-row>
                            <div>
                                <span class="dk-notification-dot" aria-hidden="true"></span>
                                <div><h3>{{ $notification->title }}</h3><p>{{ $notification->body }}</p><time>{{ $notification->created_at->format('d/m/Y H:i') }}</time></div>
                            </div>
                            <div class="dk-notification-actions">
                                <form method="POST" action="{{ route('cooperative.notifications.open', $notification) }}">@csrf<button type="submit" class="dk-inline-action">Buka</button></form>
                                @if (! $notification->read_at)
                                    <form method="POST" action="{{ route('cooperative.notifications.read', $notification) }}">@csrf<button type="submit" class="dk-inline-action">Tandai dibaca</button></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="dk-notification-empty">Belum ada notifikasi.</p>
                    @endforelse
                </div>
                @if ($notifications instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="dk-table-footer">{{ $notifications->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
