<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">Informasi Pinjaman Saya</h2>
            <span class="text-xs font-semibold uppercase text-slate-500">{{ $borrower->borrower_code }}</span>
        </div>
    </x-slot>

    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-sky-200 bg-sky-100 p-5 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-600">Saldo pembayaran di muka</p>
                <p class="mt-3 text-2xl font-extrabold text-slate-950">Rp {{ number_format((int) $advance_balance, 0, ',', '.') }}</p>
            </section>

            @forelse ($borrower->loans as $loan)
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h3 class="font-bold text-slate-900">{{ $loan->loan_code }}</h3>
                            <p class="mt-2"><span class="dk-status" data-status="{{ $loan->status }}">{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</span></p>
                        </div>
                        <div class="text-sm text-slate-700">
                            <span class="font-semibold">Total tagihan:</span>
                            Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr><th class="px-5 py-3">Cicilan</th><th class="px-5 py-3">Tagihan</th><th class="px-5 py-3">Terbayar</th><th class="px-5 py-3">Sisa</th><th class="px-5 py-3">Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($loan->installments as $installment)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-5 py-3">{{ $installment->sequence }}</td>
                                        <td class="px-5 py-3">Rp {{ number_format((int) $installment->scheduled_amount, 0, ',', '.') }}</td>
                                        <td class="px-5 py-3">Rp {{ number_format((int) $installment->paid_amount, 0, ',', '.') }}</td>
                                        <td class="px-5 py-3">Rp {{ number_format((int) $installment->outstanding_amount, 0, ',', '.') }}</td>
                                        <td class="px-5 py-3"><span class="dk-status" data-status="{{ $installment->status }}">{{ match ($installment->status) { 'pending' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'overdue' => 'Terlambat', default => ucfirst($installment->status) } }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <section class="rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500">
                    Belum ada pinjaman yang tercatat.
                </section>
            @endforelse
        </div>
    </div>
</x-app-layout>
