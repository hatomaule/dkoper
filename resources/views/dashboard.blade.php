<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-900 leading-tight">{{ __('Ringkasan DKoper') }}</h2>
            <span class="text-xs font-semibold uppercase text-slate-500">{{ auth()->user()->role }}</span>
        </div>
    </x-slot>

    <div class="py-8 bg-slate-100 min-h-[calc(100vh-65px)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @php
                $rupiah = fn ($value) => 'Rp ' . number_format((int) $value, 0, ',', '.');
                $cards = [
                    ['Modal / Kas', $rupiah($cash_balance), 'bg-sky-100 border-sky-200'],
                    ['Total Transaksi', $rupiah($total_principal_running), 'bg-violet-100 border-violet-200'],
                    ['Total Tagihan', $rupiah($total_receivable_running), 'bg-emerald-100 border-emerald-200'],
                    ['Sudah Dibayar', $rupiah($total_payment_approved), 'bg-teal-100 border-teal-200'],
                    ['Belum Dibayar', $rupiah($total_unpaid), 'bg-red-100 border-red-200'],
                    ['Biaya Administrasi', $rupiah($total_administration_fee), 'bg-amber-100 border-amber-200'],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($cards as [$label, $value, $color])
                    <section class="rounded-lg border {{ $color }} p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-slate-600">{{ $label }}</p>
                        <p class="mt-3 text-2xl font-extrabold text-slate-950">{{ $value }}</p>
                    </section>
                @endforeach

                @if (auth()->user()->isManager())
                    <section class="rounded-lg border border-teal-200 bg-teal-100 p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-slate-600">Saldo Bonus Alan</p>
                        <p class="mt-3 text-2xl font-extrabold text-slate-950">{{ $rupiah($alan_profit) }}</p>
                    </section>
                    <section class="rounded-lg border border-sky-200 bg-sky-100 p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-slate-600">Saldo Bonus Chalo</p>
                        <p class="mt-3 text-2xl font-extrabold text-slate-950">{{ $rupiah($chalo_profit) }}</p>
                    </section>
                    <section class="rounded-lg border border-orange-200 bg-orange-100 p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-slate-600">Penarikan Alan</p>
                        <p class="mt-3 text-2xl font-extrabold text-slate-950">{{ $rupiah($alan_withdrawn) }}</p>
                    </section>
                    <section class="rounded-lg border border-violet-200 bg-violet-100 p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-slate-600">Penarikan Chalo</p>
                        <p class="mt-3 text-2xl font-extrabold text-slate-950">{{ $rupiah($chalo_withdrawn) }}</p>
                    </section>
                @elseif (auth()->user()->isCashier())
                    <section class="rounded-lg border border-sky-200 bg-sky-100 p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase text-slate-600">Saldo Bonus Saya</p>
                        <p class="mt-3 text-2xl font-extrabold text-slate-950">{{ $rupiah($chalo_profit) }}</p>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
