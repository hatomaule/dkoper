<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-900">Dashboard</h2>
    </x-slot>

    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="dk-period-card dk-advance-balance">
                <p class="dk-eyebrow">Saldo pembayaran di muka</p>
                <p class="dk-finance-balance">Rp {{ number_format((int) $advance_balance, 0, ',', '.') }}</p>
            </section>

            @forelse ($borrower->loans as $loan)
                @php($installmentsBySequence = $loan->installments->keyBy('sequence'))
                <article class="dk-payment-card dk-loan-card dk-borrower-loan-card">
                    <dl class="dk-loan-card-details">
                        <div>
                            <dt>Nama</dt>
                            <dd>{{ $loan->loan_code }}<small>{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</small></dd>
                        </div>
                        <div>
                            <dt>Pokok</dt>
                            <dd>Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</dd>
                        </div>
                        @for ($sequence = 1; $sequence <= 12; $sequence++)
                            @php($installment = $installmentsBySequence->get($sequence))
                            <div class="dk-loan-installment-row @if ($sequence === 12) dk-loan-last-installment @endif">
                                <dt>Cicilan ke-{{ $sequence }}</dt>
                                <dd>
                                    {{ $installment && $installment->paid_amount > 0 ? 'Rp '.number_format((int) $installment->paid_amount, 0, ',', '.') : '—' }}
                                    @if ($installment)
                                        <small>{{ match ($installment->status) { 'pending' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas', 'overdue' => 'Terlambat', default => ucfirst($installment->status) } }}</small>
                                    @endif
                                </dd>
                            </div>
                        @endfor
                        <div class="dk-loan-card-total">
                            <dt>Total tagihan</dt>
                            <dd>Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}</dd>
                        </div>
                        <div class="dk-loan-card-total dk-loan-card-paid">
                            <dt>Total setoran</dt>
                            <dd>Rp {{ number_format((int) $loan->installments->sum('paid_amount'), 0, ',', '.') }}</dd>
                        </div>
                        <div class="dk-loan-card-total dk-loan-card-outstanding">
                            <dt>Sisa piutang</dt>
                            <dd>Rp {{ number_format((int) $loan->installments->sum('outstanding_amount'), 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <section class="rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500">
                    Belum ada pinjaman yang tercatat.
                </section>
            @endforelse
        </div>
    </div>
</x-app-layout>
