<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Riwayat Pinjaman</h2></x-slot>
    <div class="dk-loan-history-page min-h-[calc(100vh-65px)] bg-slate-100 px-4 pt-3 pb-8">
        <div class="mx-auto max-w-7xl">
            @if (session('status'))<div class="dk-form-success">{{ session('status') }}</div>@endif
            <section class="dk-data-panel min-w-0" data-table-tools>
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari nama / kode pinjaman..." data-table-search></label>
                    <label>Tampilkan
                        <select data-page-size>
                            <option value="auto" @selected(request()->query('per_page') !== 'all')>Otomatis</option>
                            <option value="all" @selected(request()->query('per_page') === 'all')>Semua</option>
                        </select>
                    </label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan">
                        <button type="button" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button>
                        <button type="button" class="is-active" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button>
                    </div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table" hidden>
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead><tr><th>Nasabah</th><th>Kode pinjaman</th><th>Status terakhir</th><th>Pokok</th><th>Total tagihan</th><th>Total setoran</th><th>Sisa piutang</th><th>Dihapus</th></tr></thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                <tr data-table-row>
                                    <td class="font-bold">{{ $loan->borrower->full_name }}</td>
                                    <td>{{ $loan->loan_code }}</td>
                                    <td>{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</td>
                                    <td class="whitespace-nowrap">Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap">Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap">Rp {{ number_format((int) $loan->installments->sum('paid_amount'), 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap">Rp {{ number_format((int) $loan->installments->sum('outstanding_amount'), 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap">{{ $loan->deleted_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="py-8 text-center text-slate-500">Belum ada pinjaman yang dihapus.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-loan-history-list dk-table-view" data-table-view="cards" hidden>
                    @forelse ($loans as $loan)
                        <article class="dk-loan-history-card" data-table-row>
                            <div class="dk-loan-history-title">
                                <div><strong>{{ $loan->borrower->full_name }}</strong><small>{{ $loan->loan_code }}</small></div>
                                <span>{{ $loan->deleted_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <dl>
                                <div><dt>Status terakhir</dt><dd>{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</dd></div>
                                <div><dt>Pokok</dt><dd>Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</dd></div>
                                <div><dt>Total tagihan</dt><dd>Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}</dd></div>
                                <div><dt>Total setoran</dt><dd>Rp {{ number_format((int) $loan->installments->sum('paid_amount'), 0, ',', '.') }}</dd></div>
                                <div><dt>Sisa piutang</dt><dd>Rp {{ number_format((int) $loan->installments->sum('outstanding_amount'), 0, ',', '.') }}</dd></div>
                            </dl>
                        </article>
                    @empty
                        <p class="dk-loan-history-empty">Belum ada pinjaman yang dihapus.</p>
                    @endforelse
                </div>
                @if ($loans instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="dk-table-footer">{{ $loans->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
