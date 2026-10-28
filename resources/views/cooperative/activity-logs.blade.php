<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Log Aktivitas</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        @php
            $activityLabels = [
                'loan.approved' => 'Pinjaman disetujui',
                'loan.pending' => 'Pinjaman menunggu persetujuan',
                'loan.rejected' => 'Pinjaman ditolak',
                'payment.approved' => 'Setoran disetujui',
                'payment.pending' => 'Setoran menunggu persetujuan',
                'payment.recorded' => 'Setoran dicatat',
                'payment.rejected' => 'Setoran ditolak',
                'payment.corrected' => 'Nominal setoran dikoreksi',
                'borrower.created' => 'Nasabah ditambahkan',
                'borrower.password_reset' => 'Password nasabah direset',
                'borrower.password_changed' => 'Password nasabah diperbarui',
                'borrower.deleted' => 'Akun nasabah dihapus',
                'staff.password_changed' => 'Password akun pengelola diperbarui',
                'cash.adjusted' => 'Kas disesuaikan',
                'profit.withdrawn' => 'Bonus ditarik',
                'profit.transferred_to_modal' => 'Bonus dipindahkan ke modal',
                'financial_data.imported' => 'Data keuangan dimasukkan',
            ];
            $formatDetails = fn ($metadata) => collect($metadata ?? [])
                ->reject(fn ($value, $key) => in_array($key, ['loan_id', 'allocations', 'advance_credit', 'borrower_code', 'username'], true) || is_array($value))
                ->map(function ($value, $key) {
                    $label = ['amount' => 'Nominal', 'staff_name' => 'Akun bonus', 'old_amount' => 'Sebelumnya', 'new_amount' => 'Sesudahnya', 'direction' => 'Arah', 'principal_amount' => 'Pokok pinjaman', 'administration_fee' => 'Biaya administrasi', 'net_disbursement' => 'Pencairan bersih', 'total_receivable' => 'Total tagihan', 'borrowers' => 'Nasabah', 'principal' => 'Pokok', 'collected' => 'Terkumpul', 'outstanding' => 'Sisa tagihan', 'receivable' => 'Tagihan', 'opening_capital' => 'Modal awal', 'ending_cash' => 'Saldo akhir'][ $key] ?? null;
                    if (! $label) return null;
                    if (in_array($key, ['direction'], true)) $value = $value === 'in' ? 'Kas masuk' : 'Kas keluar';
                    if (in_array($key, ['amount', 'old_amount', 'new_amount', 'principal_amount', 'administration_fee', 'net_disbursement', 'total_receivable', 'principal', 'collected', 'outstanding', 'receivable', 'opening_capital', 'ending_cash'], true)) $value = 'Rp '.number_format((int) $value, 0, ',', '.');
                    return $key === 'staff_name' ? "Akun bonus: {$value}" : "{$label}: {$value}";
                })
                ->filter()
                ->implode(' · ');
        @endphp
        <div class="dk-data-panel mx-auto max-w-7xl" data-table-tools>
            <div class="dk-table-toolbar">
                <label>Filter <input type="search" placeholder="Cari aktivitas..." data-table-search></label>
                <label>Tampilkan<select data-page-size><option value="5" @selected(request('per_page', '5') === '5')>5</option><option value="10" @selected(request('per_page') === '10')>10</option><option value="25" @selected(request('per_page') === '25')>25</option><option value="50" @selected(request('per_page') === '50')>50</option><option value="all" @selected(request('per_page') === 'all')>Semua</option></select></label>
                <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
            </div>
            <div class="overflow-x-auto dk-table-view" data-table-view="table">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Pengguna</th><th class="px-5 py-3">Aktivitas</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Ringkasan</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php($entityLabel = match (class_basename($log->entity_type ?? '')) { 'Loan' => 'Pinjaman', 'Payment' => 'Setoran', 'Borrower' => 'Nasabah', 'User' => 'Akun', 'CashTransaction' => 'Kas', 'ProfitTransaction' => 'Bonus', default => 'Aktivitas koperasi' })
                            <tr class="border-t border-slate-100" data-table-row>
                                <td class="dk-time-cell"><span>{{ $log->created_at->format('H:i') }}</span><small>{{ $log->created_at->format('d/m/Y') }}</small></td>
                                <td>{{ $log->user?->name ?? 'Sistem' }}</td><td>{{ $activityLabels[$log->action] ?? 'Aktivitas koperasi' }}</td><td>{{ $entityLabel }}</td>
                                <td class="dk-activity-details">{{ $formatDetails($log->metadata) ?: 'Tidak ada rincian tambahan.' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Belum ada log aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                @foreach ($logs as $log)
                    @php($entityLabel = match (class_basename($log->entity_type ?? '')) { 'Loan' => 'Pinjaman', 'Payment' => 'Setoran', 'Borrower' => 'Nasabah', 'User' => 'Akun', 'CashTransaction' => 'Kas', 'ProfitTransaction' => 'Bonus', default => 'Aktivitas koperasi' })
                    <article class="dk-payment-card" data-table-row>
                        <div><strong>{{ $activityLabels[$log->action] ?? 'Aktivitas koperasi' }}</strong><span>{{ $log->created_at->format('H:i · d/m/Y') }}</span></div>
                        <p>{{ $entityLabel }} · {{ $log->user?->name ?? 'Sistem' }}</p>
                        <small>{{ $formatDetails($log->metadata) ?: 'Tidak ada rincian tambahan.' }}</small>
                    </article>
                @endforeach
            </div>
            @if ($logs instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $logs->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
