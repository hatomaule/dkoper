<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pinjaman</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 px-4 py-8">
        <div class="mx-auto max-w-7xl space-y-5" data-table-tools>
            <section class="dk-data-panel min-w-0">
                <div class="dk-table-toolbar">
                    <button type="button" class="dk-button-primary" data-open-dialog="#loan-create-dialog">Catat Pinjaman</button>
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
                            <tr><th rowspan="2">No</th><th rowspan="2">Nama</th><th rowspan="2">Jumlah</th><th colspan="12" class="text-center">Setoran</th>@if (auth()->user()->isManager())<th rowspan="2">Aksi</th>@endif</tr>
                            <tr>@for ($sequence = 1; $sequence <= 12; $sequence++)<th class="text-center">{{ $sequence }}</th>@endfor</tr>
                        </thead>
                        <tbody>
                            <tr class="dk-loan-total-row" data-table-row>
                                <td colspan="2">TOTAL SELURUH DATA</td>
                                <td class="whitespace-nowrap">Rp {{ number_format((int) $loanTotals['principal'], 0, ',', '.') }}</td>
                                @for ($sequence = 1; $sequence <= 12; $sequence++)
                                    <td class="whitespace-nowrap text-center">Rp {{ number_format((int) ($loanTotals['installments'][$sequence] ?? 0), 0, ',', '.') }}</td>
                                @endfor
                                @if (auth()->user()->isManager())<td></td>@endif
                            </tr>
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
                                    @if (auth()->user()->isManager())
                                        <td class="whitespace-nowrap">
                                            <button type="button" class="dk-inline-action" data-open-dialog="#loan-correction-{{ $loan->id }}">Koreksi</button>
                                            @if ($loan->status === 'paid' || ((int) $loan->payments_count === 0 && $loan->created_at?->greaterThanOrEqualTo(now()->subDay())))
                                                <form method="POST" action="{{ route('cooperative.loans.archive', $loan) }}" class="inline" onsubmit="return confirm('Arsipkan pinjaman {{ $loan->loan_code }}? Riwayat keuangan tetap disimpan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="dk-inline-action dk-inline-danger">Arsipkan</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ auth()->user()->isManager() ? 16 : 15 }}" class="py-8 text-center text-slate-500">Belum ada pinjaman.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-payment-cards dk-loan-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($loans as $loan)
                        @php($installmentsBySequence = $loan->installments->keyBy('sequence'))
                        <article class="dk-payment-card dk-loan-card" data-table-row>
                            <dl class="dk-loan-card-details">
                                <div><dt>Nama</dt><dd>{{ $loan->borrower->full_name }}</dd></div>
                                <div><dt>Pokok</dt><dd>Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</dd></div>
                                @for ($sequence = 1; $sequence <= 12; $sequence++)
                                    @php($installment = $installmentsBySequence->get($sequence))
                                    <div class="dk-loan-installment-row">
                                        <dt>Cicilan {{ $sequence }}</dt>
                                        <dd>{{ $installment && $installment->paid_amount > 0 ? 'Rp '.number_format((int) $installment->paid_amount, 0, ',', '.') : '—' }}</dd>
                                    </div>
                                @endfor
                                <div class="dk-loan-card-total">
                                    <dt>Total Tagihan</dt>
                                    <dd>Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}</dd>
                                </div>
                                <div class="dk-loan-card-total dk-loan-card-paid">
                                    <dt>Total Setoran</dt>
                                    <dd>Rp {{ number_format((int) $loan->installments->sum('paid_amount'), 0, ',', '.') }}</dd>
                                </div>
                                <div class="dk-loan-card-total dk-loan-card-outstanding">
                                    <dt>Sisa Piutang</dt>
                                    <dd>Rp {{ number_format((int) $loan->installments->sum('outstanding_amount'), 0, ',', '.') }}</dd>
                                </div>
                                <div class="dk-loan-card-authority">
                                    <dt>Otoritas</dt>
                                    <dd>{{ $loan->approver?->name ?? 'Menunggu persetujuan Owner' }}</dd>
                                </div>
                            </dl>
                            @if (auth()->user()->isManager())
                                <div class="dk-loan-card-actions">
                                    <button type="button" class="dk-inline-action" data-open-dialog="#loan-correction-{{ $loan->id }}">Koreksi pinjaman</button>
                                    @if ($loan->status === 'paid' || ((int) $loan->payments_count === 0 && $loan->created_at?->greaterThanOrEqualTo(now()->subDay())))
                                        <form method="POST" action="{{ route('cooperative.loans.archive', $loan) }}" onsubmit="return confirm('Arsipkan pinjaman {{ $loan->loan_code }}? Riwayat keuangan tetap disimpan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dk-inline-action dk-inline-danger">Arsipkan</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
                @if ($loans instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $loans->links() }}</div>@endif
            </section>

            <dialog class="dk-password-native-dialog" id="loan-create-dialog" @if ($errors->any() && old('_form') === 'loan-create') data-open-on-error @endif>
                <form method="POST" action="{{ route('cooperative.loans.store') }}" class="dk-password-form dk-loan-form">
                    @csrf
                    <input type="hidden" name="_form" value="loan-create">
                    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                    <p class="dk-eyebrow">PENGAJUAN PINJAMAN</p>
                    <h3>Catat pinjaman</h3>
                    <div class="dk-loan-modal-fields">
                        <label>Nasabah
                            <select name="borrower_id" required>
                                @foreach ($borrowers as $borrower)
                                    <option value="{{ $borrower->id }}" @selected(old('borrower_id') == $borrower->id)>{{ $borrower->full_name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Pokok pinjaman
                            <input name="principal_amount" type="text" inputmode="numeric" data-format-rupiah data-min="1000" required value="{{ old('principal_amount') }}" placeholder="1.000.000">
                        </label>
                    </div>
                    <p class="dk-modal-note">{{ auth()->user()->isManager() ? 'Pinjaman Owner langsung dicatat.' : 'Pinjaman Admin menunggu persetujuan Owner.' }}</p>
                    <button class="dk-button-primary">Catat Pinjaman</button>
                </form>
            </dialog>
            @foreach ($loans as $loan)
                @if (auth()->user()->isManager())
                    <dialog class="dk-password-native-dialog dk-loan-correction-dialog" id="loan-correction-{{ $loan->id }}" @if ($errors->any() && old('_form') === 'loan-correction-'.$loan->id) data-open-on-error @endif>
                        <form method="POST" action="{{ route('cooperative.loans.correct', $loan) }}" class="dk-password-form dk-loan-form">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="_form" value="loan-correction-{{ $loan->id }}">
                            <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                            <p class="dk-eyebrow">KOREKSI PINJAMAN · {{ $loan->loan_code }}</p>
                            <h3>{{ $loan->borrower->full_name }}</h3>
                            <p class="dk-modal-note">Nama nasabah tetap. Koreksi nilai keuangan atau 12 setoran akan merekonstruksi cicilan, bonus, dan uang muka tanpa menghapus riwayat.</p>
                            <div class="dk-loan-modal-fields">
                                <label>Pokok pinjaman (Rp)<input name="principal_amount" type="number" min="1000" required value="{{ old('principal_amount', $loan->principal_amount) }}"></label>
                                <label>Biaya administrasi (Rp)<input name="administration_fee" type="number" min="0" required value="{{ old('administration_fee', $loan->administration_fee) }}"></label>
                                <label>Bunga (Rp)<input name="interest_amount" type="number" min="0" required value="{{ old('interest_amount', $loan->interest_amount) }}"></label>
                            </div>
                            <div class="dk-loan-correction-schedule">
                                @foreach ($loan->installments->sortBy('sequence')->values() as $index => $installment)
                                    <label>Setoran {{ $installment->sequence }} (Rp)<input name="installments[{{ $index }}]" type="number" min="1" required value="{{ old('installments.'.$index, $installment->scheduled_amount) }}"></label>
                                @endforeach
                            </div>
                            <p class="dk-modal-note">Jumlah 12 setoran harus sama dengan pokok + bunga.</p>
                            <button class="dk-button-primary">Simpan Koreksi</button>
                        </form>
                    </dialog>
                @endif
            @endforeach
        </div>
    </div>
</x-app-layout>
