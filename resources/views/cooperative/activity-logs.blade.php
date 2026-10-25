<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Log Aktivitas</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Pengguna</th><th class="px-5 py-3">Aktivitas</th><th class="px-5 py-3">Entitas</th><th class="px-5 py-3">Detail</th><th class="px-5 py-3">Alamat IP</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-t border-slate-100"><td class="px-5 py-3">{{ $log->created_at->format('d/m/Y H:i:s') }}</td><td class="px-5 py-3">{{ $log->user?->name ?? 'Sistem' }}</td><td class="px-5 py-3">{{ $log->action }}</td><td class="px-5 py-3">{{ class_basename($log->entity_type ?? '') }} #{{ $log->entity_id }}</td><td class="max-w-md px-5 py-3 text-xs text-slate-600">{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td><td class="px-5 py-3">{{ $log->ip_address }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Belum ada log aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>
