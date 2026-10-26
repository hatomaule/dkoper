<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Riwayat Keuangan</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="grid items-stretch gap-5 lg:grid-cols-[minmax(240px,290px)_minmax(0,1fr)]">
                <form method="POST" action="{{ route('cooperative.finances.store') }}" class="dk-form-panel self-start">
                    @csrf
                    <h3 class="font-bold text-slate-900">Catat Perubahan Kas</h3>
                    <p class="mt-2 text-sm text-slate-600">Gunakan untuk saldo modal awal atau transaksi kas manual yang memiliki bukti.</p>
                    @if ($errors->any())<p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p>@endif
                    @if (session('status'))<p class="mt-3 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                    <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Arah transaksi</label>
                    <select name="direction" required class="mt-2 w-full rounded-md border-slate-300">
                        <option value="in">Kas masuk</option><option value="out">Kas keluar</option>
                    </select>
                    <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Nominal (Rp)</label>
                    <input name="amount" type="text" inputmode="numeric" data-format-rupiah data-min="1" required value="{{ old('amount') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Keterangan / referensi</label>
                    <input name="description" maxlength="255" required value="{{ old('description') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <button class="dk-button-primary mt-5 w-fit">Catat</button>
                </form>

                <section class="dk-chart-panel dk-finance-summary">
                    <div>
                        <p class="dk-eyebrow">SALDO KAS SAAT INI</p>
                        <p class="dk-finance-balance">Rp {{ number_format($balance, 0, ',', '.') }}</p>
                    </div>
                    <div class="dk-finance-cash-chart">
                        <div class="dk-section-title"><div><span>ARUS KAS</span><h3>Masuk dan keluar</h3></div><small>12 bulan</small></div>
                        <div class="dk-finance-chart-legend"><span><i class="is-in"></i>Masuk</span><span><i class="is-out"></i>Keluar</span></div>
                        @foreach ($monthlyCashFlow as $month)
                            @php($inWidth = (int) round(($month['in'] / $maxMonthlyCashFlow) * 100))
                            @php($outWidth = (int) round(($month['out'] / $maxMonthlyCashFlow) * 100))
                            <div class="dk-finance-chart-row" title="{{ $month['label'] }} · Masuk Rp {{ number_format($month['in'], 0, ',', '.') }} · Keluar Rp {{ number_format($month['out'], 0, ',', '.') }}">
                                <span>{{ $month['label'] }}</span>
                                <div class="dk-finance-chart-track">
                                    @if ($inWidth > 0)<i class="is-in" style="width: {{ $inWidth }}%"></i>@endif
                                    @if ($outWidth > 0)<i class="is-out" style="width: {{ $outWidth }}%"></i>@endif
                                </div>
                                <small>{{ number_format($month['in'] / 1000000, 1, ',', '.') }} / {{ number_format($month['out'] / 1000000, 1, ',', '.') }} jt</small>
                            </div>
                        @endforeach
                        <p class="dk-finance-chart-note">Nilai di kanan menunjukkan kas masuk / keluar dalam juta rupiah.</p>
                    </div>
                </section>
            </div>

            <section class="dk-data-panel" data-table-tools>
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari transaksi..." data-table-search></label>
                    <label>Tampilkan<select data-page-size><option value="5" @selected(request('per_page', '5') === '5')>5</option><option value="10" @selected(request('per_page') === '10')>10</option><option value="25" @selected(request('per_page') === '25')>25</option><option value="50" @selected(request('per_page') === '50')>50</option><option value="all" @selected(request('per_page') === 'all')>Semua</option></select></label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Keterangan</th><th class="px-5 py-3">Kas masuk</th><th class="px-5 py-3">Kas keluar</th><th class="px-5 py-3">Saldo</th><th class="px-5 py-3">Petugas</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                @php($typeLabels = ['loan_disbursement' => 'Pencairan pinjaman', 'administration_fee' => 'Biaya administrasi', 'installment_payment' => 'Pembayaran cicilan', 'payment_correction' => 'Koreksi setoran', 'staff_withdrawal' => 'Penarikan bonus', 'cash_adjustment' => 'Penyesuaian kas'])
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
                        @php($typeLabels = ['loan_disbursement' => 'Pencairan pinjaman', 'administration_fee' => 'Biaya administrasi', 'installment_payment' => 'Pembayaran cicilan', 'payment_correction' => 'Koreksi setoran', 'staff_withdrawal' => 'Penarikan bonus', 'cash_adjustment' => 'Penyesuaian kas'])
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
        </div>
    </div>
</x-app-layout>
