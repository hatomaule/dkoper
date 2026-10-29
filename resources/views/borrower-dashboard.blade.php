<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-900">Informasi Pinjaman Saya</h2>
            <span class="text-xs font-semibold uppercase text-slate-500">{{ $borrower->borrower_code }}</span>
        </div>
    </x-slot>

    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="dk-period-card dk-advance-balance">
                <p class="dk-eyebrow">Saldo pembayaran di muka</p>
                <p class="dk-finance-balance">Rp {{ number_format((int) $advance_balance, 0, ',', '.') }}</p>
            </section>

            @forelse ($borrower->loans as $loan)
                <section class="dk-borrower-loan-card">
                    <div class="dk-borrower-loan-heading">
                        <div>
                            <h3 class="font-bold text-slate-900">{{ $loan->loan_code }}</h3>
                            <p class="mt-2"><span class="dk-status" data-status="{{ $loan->status }}">{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</span></p>
                        </div>
                        <div class="text-sm text-slate-700">
                            <span class="font-semibold">Total tagihan:</span>
                            Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="dk-borrower-installments">
                        @foreach ($loan->installments as $installment)
                            <article class="dk-payment-card dk-borrower-installment-card" data-table-row>
                                <div>
                                    <strong>Cicilan ke-{{ $installment->sequence }}</strong>
                                    <span class="dk-status" data-status="{{ $installment->status }}">{{ match ($installment->status) { 'pending' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'overdue' => 'Terlambat', default => ucfirst($installment->status) } }}</span>
                                </div>
                                <dl>
                                    <div><dt>Tagihan</dt><dd>Rp {{ number_format((int) $installment->scheduled_amount, 0, ',', '.') }}</dd></div>
                                    <div><dt>Terbayar</dt><dd>Rp {{ number_format((int) $installment->paid_amount, 0, ',', '.') }}</dd></div>
                                    <div><dt>Sisa</dt><dd>Rp {{ number_format((int) $installment->outstanding_amount, 0, ',', '.') }}</dd></div>
                                </dl>
                            </article>
                        @endforeach
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
