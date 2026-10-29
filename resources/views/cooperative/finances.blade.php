<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Riwayat Keuangan</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl space-y-6">
            @if ($errors->any())<div class="dk-form-alert mb-3">{{ $errors->first() }}</div>@endif
            @if (session('status'))<div class="dk-form-success mb-3">{{ session('status') }}</div>@endif
                <section class="dk-chart-panel dk-finance-summary">
                    <div>
                        <p class="dk-eyebrow">SALDO KAS SAAT INI</p>
                        <p class="dk-finance-balance">Rp {{ number_format($balance, 0, ',', '.') }}</p>
                    </div>
                </section>
            <section class="dk-data-panel" data-table-tools>
                <div class="dk-table-toolbar">
                    <button type="button" class="dk-button-primary" data-open-dialog="#cash-adjustment-dialog">Catat Perubahan Kas</button>
                    <label>Filter <input type="search" placeholder="Cari transaksi..." data-table-search></label>
                    <label>Tampilkan<select data-page-size><option value="5" @selected(request('per_page', '5') === '5')>5</option><option value="10" @selected(request('per_page') === '10')>10</option><option value="25" @selected(request('per_page') === '25')>25</option><option value="50" @selected(request('per_page') === '50')>50</option><option value="all" @selected(request('per_page') === 'all')>Semua</option></select></label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Keterangan</th><th class="px-5 py-3">Kas masuk</th><th class="px-5 py-3">Kas keluar</th><th class="px-5 py-3">Saldo</th><th class="px-5 py-3">Petugas</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                @php($typeLabels = ['loan_disbursement' => 'Pencairan pinjaman', 'administration_fee' => 'Biaya administrasi', 'installment_payment' => 'Pembayaran cicilan', 'payment_correction' => 'Koreksi setoran', 'loan_correction' => 'Koreksi data pinjaman', 'staff_withdrawal' => 'Penarikan bonus', 'cash_adjustment' => 'Penyesuaian kas'])
                                <tr class="border-t border-slate-100" data-table-row>
                                    <td class="dk-time-cell"><span>{{ $transaction->created_at->format('H:i') }}</span><small>{{ $transaction->created_at->format('d/m/Y') }}</small></td>
                                    <td>{{ $typeLabels[$transaction->type] ?? 'Transaksi kas' }}</td>
                                    <td>{{ $transaction->description }} @if ($transaction->loan) ({{ $transaction->loan->loan_code }}) @endif</td>
                                    <td>{{ $transaction->direction === 'in' ? 'Rp '.number_format((int) $transaction->amount, 0, ',', '.') : '—' }}</td>
                                    <td>{{ $transaction->direction === 'out' ? 'Rp '.number_format((int) $transaction->amount, 0, ',', '.') : '—' }}</td>
                                    <td class="font-semibold">Rp {{ number_format((int) $transaction->balance_after, 0, ',', '.') }}</td>
                                    <td>{{ $transaction->creator->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Belum ada transaksi keuangan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($transactions as $transaction)
                        @php($typeLabels = ['loan_disbursement' => 'Pencairan pinjaman', 'administration_fee' => 'Biaya administrasi', 'installment_payment' => 'Pembayaran cicilan', 'payment_correction' => 'Koreksi setoran', 'loan_correction' => 'Koreksi data pinjaman', 'staff_withdrawal' => 'Penarikan bonus', 'cash_adjustment' => 'Penyesuaian kas'])
                        <article class="dk-payment-card" data-table-row>
                            <div><strong>{{ $typeLabels[$transaction->type] ?? 'Transaksi kas' }}</strong><span>{{ $transaction->created_at->format('H:i · d/m/Y') }}</span></div>
                            <p>{{ $transaction->description }} @if ($transaction->loan) · {{ $transaction->loan->loan_code }} @endif</p>
                            <b class="{{ $transaction->direction === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $transaction->direction === 'in' ? '+' : '−' }}Rp {{ number_format((int) $transaction->amount, 0, ',', '.') }}</b>
                            <small>Saldo setelah transaksi Rp {{ number_format((int) $transaction->balance_after, 0, ',', '.') }}</small>
                            <small>Dicatat oleh {{ $transaction->creator->name }}</small>
                        </article>
                    @endforeach
                </div>
                @if ($transactions instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="dk-table-footer">{{ $transactions->links() }}</div>
                @endif
            </section>
            <dialog class="dk-password-native-dialog" id="cash-adjustment-dialog" @if ($errors->any() && old('_form') === 'cash-adjustment') data-open-on-error @endif>
                <form method="POST" action="{{ route('cooperative.finances.store') }}" class="dk-password-form">
                    @csrf
                    <input type="hidden" name="_form" value="cash-adjustment">
                    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                    <p class="dk-eyebrow">KEUANGAN KOPERASI</p>
                    <h3>Catat perubahan kas</h3>
                    <p class="dk-modal-note">Gunakan untuk saldo modal awal atau transaksi kas manual yang memiliki bukti.</p>
                    <label>Arah transaksi
                        <select name="direction" required><option value="in" @selected(old('direction') === 'in')>Kas masuk</option><option value="out" @selected(old('direction') === 'out')>Kas keluar</option></select>
                    </label>
                    <label>Nominal (Rp)<input name="amount" type="text" inputmode="numeric" data-format-rupiah data-min="1" required value="{{ old('amount') }}"></label>
                    <label>Keterangan / referensi<input name="description" maxlength="255" required value="{{ old('description') }}"></label>
                    <button class="dk-button-primary">Catat Perubahan Kas</button>
                </form>
            </dialog>
        </div>
    </div>
</x-app-layout>
