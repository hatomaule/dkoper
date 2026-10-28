<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="dk-dashboard-page min-h-[calc(100vh-65px)]">
        @if (session('status'))<div class="dk-form-success mb-3">{{ session('status') }}</div>@endif
        @php
            $rupiah = fn ($value) => 'Rp ' . number_format((int) $value, 0, ',', '.');
            $maxWeeklyCash = max(1, (int) $weekly_cash_flow->flatMap(fn ($week) => [$week['debit'], $week['credit']])->max());
            $weeklyDebitPoints = $weekly_cash_flow->values()->map(fn ($week, $index) => ($index * (100 / max(1, $weekly_cash_flow->count() - 1))) . ',' . (100 - (int) round(($week['debit'] / $maxWeeklyCash) * 82)))->implode(' ');
            $weeklyCreditPoints = $weekly_cash_flow->values()->map(fn ($week, $index) => ($index * (100 / max(1, $weekly_cash_flow->count() - 1))) . ',' . (100 - (int) round(($week['credit'] / $maxWeeklyCash) * 82)))->implode(' ');
        @endphp

        <div class="dk-dashboard-charts">
            <section class="dk-chart-panel dk-weekly-chart">
                <div class="dk-section-title"><div><span>ARUS KAS</span><h3>Debit dan kredit mingguan</h3></div><small>Bulan berjalan</small></div>
                <div class="dk-chart-axis-labels"><span>{{ $rupiah($maxWeeklyCash) }}</span><span>{{ $rupiah((int) ($maxWeeklyCash / 2)) }}</span><span>Rp 0</span></div>
                <svg class="dk-weekly-line-chart" viewBox="0 0 400 120" preserveAspectRatio="none" role="img" aria-label="Grafik debit dan kredit per minggu pada bulan berjalan">
                    <path d="M0 18H400M0 60H400M0 102H400" class="dk-chart-grid"></path>
                    <polyline points="{{ $weeklyDebitPoints }}" class="dk-chart-line dk-chart-debit"></polyline>
                    <polyline points="{{ $weeklyCreditPoints }}" class="dk-chart-line dk-chart-credit"></polyline>
                    @foreach ($weekly_cash_flow->values() as $index => $week)
                        @php($x = $index * (400 / max(1, $weekly_cash_flow->count() - 1)))
                        @php($debitY = 102 - (int) round(($week['debit'] / $maxWeeklyCash) * 84))
                        @php($creditY = 102 - (int) round(($week['credit'] / $maxWeeklyCash) * 84))
                        <circle cx="{{ $x }}" cy="{{ $debitY }}" r="3" class="dk-chart-point dk-chart-debit-point"><title>{{ $week['label'] }} · Debit {{ $rupiah($week['debit']) }}</title></circle>
                        <circle cx="{{ $x }}" cy="{{ $creditY }}" r="3" class="dk-chart-point dk-chart-credit-point"><title>{{ $week['label'] }} · Kredit {{ $rupiah($week['credit']) }}</title></circle>
                    @endforeach
                </svg>
                <div class="dk-chart-labels">@foreach ($weekly_cash_flow as $week)<span>{{ $week['label'] }}</span>@endforeach</div>
                <div class="dk-chart-legend">
                    <span><i class="dk-legend-debit"></i>Debit · Kas masuk</span>
                    <span><i class="dk-legend-credit"></i>Kredit · Kas keluar</span>
                </div>
            </section>

            <section class="dk-chart-panel dk-monthly-amount-chart">
                <div class="dk-section-title"><div><span>ARUS KAS</span><h3>Nominal transaksi per bulan</h3></div><small>12 bulan</small></div>
                <div class="dk-monthly-bars" role="img" aria-label="Nominal transaksi kas per bulan selama 12 bulan">
                    @foreach ($monthly_transaction_amounts as $month)
                        <div class="dk-monthly-bar-row">
                            <span>{{ $month['label'] }}</span>
                            <div class="dk-monthly-bar-track"><i @class(['is-empty' => $month['amount'] === 0]) style="width: {{ (int) round(($month['amount'] / $max_monthly_transaction_amount) * 100) }}%"></i></div>
                            <b title="{{ $rupiah($month['amount']) }}">{{ $rupiah($month['amount']) }}</b>
                        </div>
                    @endforeach
                </div>
                <div class="dk-chart-legend"><span><i class="dk-legend-transactions"></i>Total nominal transaksi kas</span></div>
            </section>
        </div>

        <div class="dk-dashboard-section">
            <div class="dk-section-title"><div><span>KEUANGAN</span><h3>Posisi koperasi</h3></div></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['Kas tersedia', $rupiah($cash_balance)],
                    ['Pokok pinjaman aktif', $rupiah($total_principal_running)],
                    ['Total tagihan aktif', $rupiah($total_receivable_running)],
                    ['Setoran diterima', $rupiah($total_payment_approved)],
                    ['Sisa tagihan', $rupiah($total_unpaid)],
                    ['Biaya administrasi', $rupiah($total_administration_fee)],
                    ['Jumlah nasabah', number_format((int) $borrower_count, 0, ',', '.').' orang'],
                ] as $index => [$label, $value])
                    <section class="dk-stat-card dk-stat-tone-{{ $index % 6 }}">
                        <div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>{{ $label }}</p></div>
                        <p class="dk-stat-value">{{ $value }}</p>
                    </section>
                @endforeach
            </div>
        </div>

        <div class="dk-dashboard-section">
            <div class="dk-section-title"><div><span>BONUS</span><h3>Saldo dan proyeksi bonus</h3></div></div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ([
                    [$owner_name, $owner_profit, $owner_earned_bonus, $owner_future_bonus, 'dk-bonus-owner'],
                    [$admin_name, $admin_profit, $admin_earned_bonus, $admin_future_bonus, 'dk-bonus-admin'],
                ] as [$staffName, $available, $earned, $projected, $tone])
                    <section class="dk-bonus-summary {{ $tone }}">
                        <div class="dk-bonus-earned"><span>SALDO BONUS TERSEDIA</span><h4>{{ $staffName }}</h4><strong>{{ $rupiah($available) }}</strong><small>Setelah penarikan dan transfer ke modal</small></div>
                        <div class="dk-bonus-total"><span>TOTAL BONUS KESELURUHAN</span><strong>{{ $rupiah((int) $earned + (int) $projected) }}</strong><small>Terealisasi {{ $rupiah($earned) }} + proyeksi pinjaman aktif {{ $rupiah($projected) }}</small></div>
                    </section>
                @endforeach
            </div>
        </div>

        <div class="dk-dashboard-section">
            <div class="dk-section-title"><div><span>SETORAN DISETUJUI</span><h3>Laporan pemasukan</h3></div></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ([['Hari ini', $daily_payments], ['Minggu ini', $weekly_payments], ['Bulan ini', $monthly_total_payments]] as [$label, $total])
                    <section class="dk-period-card"><span>{{ $label }}</span><strong>{{ $rupiah($total) }}</strong><small>Transaksi pembayaran disetujui</small></section>
                @endforeach
            </div>
        </div>

    </div>
</x-app-layout>
