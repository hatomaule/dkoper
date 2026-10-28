<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pinjaman</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 px-4 py-8">
        <div class="mx-auto max-w-7xl space-y-5" data-table-tools>
            <form method="POST" action="{{ route('cooperative.loans.store') }}" class="dk-form-panel">
                @csrf
                <h3 class="font-bold text-slate-900">Ajukan Pinjaman</h3>
                @if (session('status'))<div class="mt-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="mt-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
                <div class="mt-4 grid items-end gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(180px,1fr)_auto]">
                    <label class="block text-xs font-bold uppercase text-slate-600">Nasabah
                        <select name="borrower_id" required class="mt-2 w-full rounded-md border-slate-300">
                            @foreach ($borrowers as $borrower)
                                <option value="{{ $borrower->id }}" @selected(old('borrower_id') == $borrower->id)>{{ $borrower->full_name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs font-bold uppercase text-slate-600">Pokok pinjaman
                        <input name="principal_amount" type="text" inputmode="numeric" data-format-rupiah data-min="1000" required value="{{ old('principal_amount') }}" class="mt-2 w-full rounded-md border-slate-300" placeholder="1.000.000">
                    </label>
                    <button class="dk-button-primary h-fit">Catat pinjaman</button>
                </div>
            </form>

            <section class="dk-data-panel min-w-0">
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari nasabah / pinjaman..." data-table-search></label>
                    <label>Tampilkan
                        <select data-page-size>
                            <option value="5" @selected($perPage === 5)>5</option>
                            <option value="10" @selected($perPage === 10)>10</option>
                            <option value="25" @selected($perPage === 25)>25</option>
                            <option value="50" @selected($perPage === 50)>50</option>
                            <option value="all" @selected($perPage === 'all')>Semua</option>
                        </select>
                    </label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan">
                        <button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button>
                        <button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button>
                    </div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm dk-loan-ledger">
                        <thead>
                            <tr><th rowspan="2">No</th><th rowspan="2">Nama</th><th rowspan="2">Jumlah</th><th colspan="12" class="text-center">Setoran</th></tr>
                            <tr>@for ($sequence = 1; $sequence <= 12; $sequence++)<th class="text-center">{{ $sequence }}</th>@endfor</tr>
                        </thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                @php($installmentsBySequence = $loan->installments->keyBy('sequence'))
                                @php($loanStatus = match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) })
                                <tr data-table-row>
                                    <td>{{ $loans instanceof \Illuminate\Contracts\Pagination\Paginator ? $loans->firstItem() + $loop->index : $loop->iteration }}</td>
                                    <td class="font-bold">
                                        {{ $loan->borrower->full_name }}
                                        <small class="block text-xs font-normal text-slate-500">{{ $loan->loan_code }} · {{ $loanStatus }}</small>
                                    </td>
                                    <td class="whitespace-nowrap">Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</td>
                                    @for ($sequence = 1; $sequence <= 12; $sequence++)
                                        @php($installment = $installmentsBySequence->get($sequence))
                                        <td class="whitespace-nowrap text-center">
                                            {{ $installment && $installment->paid_amount > 0 ? 'Rp '.number_format((int) $installment->paid_amount, 0, ',', '.') : '—' }}
                                        </td>
                                    @endfor
                                </tr>
                            @empty
                                <tr><td colspan="15" class="py-8 text-center text-slate-500">Belum ada pinjaman.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-bold">
                                <td colspan="2">TOTAL SELURUH DATA</td>
                                <td class="whitespace-nowrap">Rp {{ number_format((int) $loanTotals['principal'], 0, ',', '.') }}</td>
                                @for ($sequence = 1; $sequence <= 12; $sequence++)
                                    <td class="whitespace-nowrap text-center">Rp {{ number_format((int) ($loanTotals['installments'][$sequence] ?? 0), 0, ',', '.') }}</td>
                                @endfor
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($loans as $loan)
                        @php($installmentsBySequence = $loan->installments->keyBy('sequence'))
                        @php($loanStatus = match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) })
                        <article class="dk-payment-card" data-table-row>
                            <div>
                                <strong>{{ $loan->borrower->full_name }}</strong>
                                <span class="dk-status" data-status="{{ $loan->status }}">{{ $loanStatus }}</span>
                            </div>
                            <small>{{ $loan->loan_code }} · Jumlah pinjaman Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</small>
                            <div class="dk-loan-installment-grid">
                                @for ($sequence = 1; $sequence <= 12; $sequence++)
                                    @php($installment = $installmentsBySequence->get($sequence))
                                    <div><small>{{ $sequence }}</small><strong>{{ $installment && $installment->paid_amount > 0 ? number_format((int) $installment->paid_amount, 0, ',', '.') : '—' }}</strong></div>
                                @endfor
                            </div>
                        </article>
                    @endforeach
                    <article class="dk-payment-card dk-loan-total-card">
                        <strong>TOTAL SELURUH DATA</strong>
                        <b>Jumlah pinjaman: Rp {{ number_format((int) $loanTotals['principal'], 0, ',', '.') }}</b>
                        <div class="dk-loan-installment-grid">
                            @for ($sequence = 1; $sequence <= 12; $sequence++)
                                <div><small>{{ $sequence }}</small><strong>{{ number_format((int) ($loanTotals['installments'][$sequence] ?? 0), 0, ',', '.') }}</strong></div>
                            @endfor
                        </div>
                    </article>
                </div>
                @if ($loans instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $loans->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
