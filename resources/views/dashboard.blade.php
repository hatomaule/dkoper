<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">Dashboard</h2>
            <p class="mt-1 text-sm text-slate-500">Gambaran singkat kondisi koperasi.</p>
        </div>
    </x-slot>

    <div class="min-h-[calc(100vh-65px)]">
        <section class="dk-welcome-panel">
            <div>
                <p class="dk-eyebrow">DASHBOARD KOPERASI</p>
                <h3>Halo, {{ auth()->user()->name }} 👋</h3>
                <p>Ringkasan operasional dan posisi keuangan saat ini.</p>
            </div>
            <span class="dk-welcome-mark" aria-hidden="true"><img src="{{ asset('logo.svg') }}" alt=""></span>
        </section>

        @php
            $rupiah = fn ($value) => 'Rp ' . number_format((int) $value, 0, ',', '.');
            $maxMonthly = max(1, (int) $monthly_payments->max('amount'));
            $monthlyPoints = $monthly_payments->values()->map(function ($item, $index) use ($maxMonthly) {
                return ($index * 100) . ',' . (100 - (int) round(($item['amount'] / $maxMonthly) * 82));
            })->implode(' ');
        @endphp

        <div class="dk-dashboard-section">
            <div class="dk-section-title"><div><span>KEUANGAN</span><h3>Posisi koperasi</h3></div></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
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

        @if (auth()->user()->isManager())
            <div class="dk-dashboard-section">
                <div class="dk-section-title"><div><span>BONUS STAF</span><h3>Saldo dan proyeksi bonus</h3></div></div>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ([
                        [$owner_name, $owner_profit, $owner_future_bonus, 'dk-stat-tone-0'],
                        [$admin_name, $admin_profit, $admin_future_bonus, 'dk-stat-tone-3'],
                    ] as [$staffName, $available, $projected, $tone])
                        <section class="dk-bonus-summary {{ $tone }}">
                            <div><span>SALDO BONUS</span><h4>{{ $staffName }}</h4><strong>{{ $rupiah($available) }}</strong><small>Bonus yang sudah diperoleh dari cicilan lunas</small></div>
                            <div><span>TOTAL BONUS PINJAMAN AKTIF</span><strong>{{ $rupiah((int) $available + (int) $projected) }}</strong><small>Saldo saat ini + bagian bonus dari cicilan yang belum lunas</small></div>
                        </section>
                    @endforeach
                </div>
            </div>
        @elseif (auth()->user()->isCashier())
            <div class="dk-dashboard-section grid gap-3 sm:grid-cols-2">
                <section class="dk-stat-card dk-stat-tone-0"><div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Saldo bonus saya</p></div><p class="dk-stat-value">{{ $rupiah($my_profit) }}</p></section>
                <section class="dk-stat-card dk-stat-tone-3"><div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Bonus belum diperoleh</p></div><p class="dk-stat-value">{{ $rupiah($admin_future_bonus) }}</p></section>
            </div>
        @endif

        <div class="dk-dashboard-section">
            <div class="dk-section-title"><div><span>SETORAN DISETUJUI</span><h3>Laporan pemasukan</h3></div></div>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([['Hari ini', $daily_payments], ['Minggu ini', $weekly_payments], ['Bulan ini', $monthly_total_payments]] as [$label, $total])
                    <section class="dk-period-card"><span>{{ $label }}</span><strong>{{ $rupiah($total) }}</strong><small>Transaksi pembayaran disetujui</small></section>
                @endforeach
            </div>
        </div>

        <section class="dk-chart-panel">
            <div class="dk-section-title"><div><span>TREN PEMBAYARAN</span><h3>Setoran bulanan</h3></div><small>6 bulan terakhir</small></div>
            <div class="dk-chart-wrap">
                <div class="dk-chart-y"><span>{{ $rupiah($maxMonthly) }}</span><span>{{ $rupiah((int) ($maxMonthly / 2)) }}</span><span>Rp 0</span></div>
                <svg class="dk-month-chart" viewBox="0 0 500 110" preserveAspectRatio="none" role="img" aria-label="Grafik pembayaran bulanan">
                    <path d="M0 18H500M0 59H500M0 100H500" class="dk-chart-grid"></path>
                    <polyline points="{{ $monthlyPoints }}" class="dk-chart-line"></polyline>
                    @foreach ($monthly_payments->values() as $index => $item)
                        @php($x = $index * 100)
                        @php($y = 100 - (int) round(($item['amount'] / $maxMonthly) * 82))
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="3" class="dk-chart-point"><title>{{ $item['label'] }} · {{ $rupiah($item['amount']) }}</title></circle>
                    @endforeach
                </svg>
            </div>
            <div class="dk-chart-labels">@foreach ($monthly_payments as $item)<span>{{ $item['label'] }}</span>@endforeach</div>
            <div class="dk-chart-legend"><i></i><span>Jumlah setoran disetujui</span></div>
        </section>
    </div>
</x-app-layout>
