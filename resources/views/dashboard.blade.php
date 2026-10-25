<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 leading-tight">{{ __('Ringkasan') }}</h2>
                <p class="mt-1 text-sm text-slate-500">Gambaran singkat kondisi keuangan koperasi.</p>
            </div>
            <span class="text-xs font-semibold uppercase text-slate-500">{{ auth()->user()->role }}</span>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-100 min-h-[calc(100vh-65px)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="dk-welcome-panel">
                <div>
                    <p class="dk-eyebrow">DASHBOARD KOPERASI</p>
                    <h3>Halo, {{ auth()->user()->name }} 👋</h3>
                    <p>Berikut ringkasan operasional dan posisi keuangan saat ini.</p>
                </div>
                <span class="dk-welcome-mark" aria-hidden="true">DK</span>
            </section>

            @php
                $rupiah = fn ($value) => 'Rp ' . number_format((int) $value, 0, ',', '.');
                $cards = [
                    ['Kas tersedia', $rupiah($cash_balance)],
                    ['Total pinjaman', $rupiah($total_principal_running)],
                    ['Total tagihan', $rupiah($total_receivable_running)],
                    ['Pembayaran diterima', $rupiah($total_payment_approved)],
                    ['Sisa tagihan', $rupiah($total_unpaid)],
                    ['Biaya administrasi', $rupiah($total_administration_fee)],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $index => [$label, $value])
                    <section class="dk-stat-card dk-stat-tone-{{ $index }}">
                        <div class="dk-stat-heading">
                            <span class="dk-stat-dot"></span>
                            <p>{{ $label }}</p>
                        </div>
                        <p class="dk-stat-value">{{ $value }}</p>
                    </section>
                @endforeach

                @if (auth()->user()->isManager())
                    <section class="dk-stat-card dk-stat-tone-3">
                        <div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Saldo bonus Alan</p></div>
                        <p class="dk-stat-value">{{ $rupiah($alan_profit) }}</p>
                    </section>
                    <section class="dk-stat-card dk-stat-tone-0">
                        <div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Saldo bonus Chalo</p></div>
                        <p class="dk-stat-value">{{ $rupiah($chalo_profit) }}</p>
                    </section>
                    <section class="dk-stat-card dk-stat-tone-4">
                        <div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Penarikan Alan</p></div>
                        <p class="dk-stat-value">{{ $rupiah($alan_withdrawn) }}</p>
                    </section>
                    <section class="dk-stat-card dk-stat-tone-2">
                        <div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Penarikan Chalo</p></div>
                        <p class="dk-stat-value">{{ $rupiah($chalo_withdrawn) }}</p>
                    </section>
                @elseif (auth()->user()->isCashier())
                    <section class="dk-stat-card dk-stat-tone-0">
                        <div class="dk-stat-heading"><span class="dk-stat-dot"></span><p>Saldo bonus saya</p></div>
                        <p class="dk-stat-value">{{ $rupiah($my_profit) }}</p>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
