<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Log Aktivitas</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" data-table-tools>
            <div class="dk-table-toolbar"><label>Filter <input type="search" placeholder="Cari aktivitas..." data-table-search></label><label>Tampilkan<select data-page-size><option value="10" @selected(request('per_page', '10') === '10')>10</option><option value="25" @selected(request('per_page') === '25')>25</option><option value="50" @selected(request('per_page') === '50')>50</option><option value="all" @selected(request('per_page') === 'all')>Semua</option></select></label></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Pengguna</th><th class="px-5 py-3">Aktivitas</th><th class="px-5 py-3">Entitas</th><th class="px-5 py-3">Detail</th><th class="px-5 py-3">Alamat IP</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-t border-slate-100" data-table-row><td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $log->user?->name ?? 'Sistem' }}</td><td>{{ $log->action }}</td><td>{{ class_basename($log->entity_type ?? '') }} #{{ $log->entity_id }}</td><td class="dk-activity-details">{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td><td>{{ $log->ip_address }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Belum ada log aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="border-t border-slate-100 px-5 py-3">{{ $logs->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
