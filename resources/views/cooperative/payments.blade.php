<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pembayaran</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl">
            @if ($errors->any())<div class="dk-form-alert mb-3">{{ $errors->first() }}</div>@endif
            @if (session('status'))<div class="dk-form-success mb-3">{{ session('status') }}</div>@endif
            <section class="dk-data-panel min-w-0" data-table-tools>
                <div @class(['dk-table-toolbar', 'dk-payment-management-toolbar' => !auth()->user()->isBorrower()])>
                    @unless (auth()->user()->isBorrower())
                        <div class="dk-payment-toolbar-actions">
                            <button type="button" class="dk-button-primary" data-open-dialog="#payment-create-dialog" data-payment-mode-trigger="installment">Catat Pembayaran</button>
                            <button type="button" class="dk-button-secondary" data-open-dialog="#payment-create-dialog" data-payment-mode-trigger="payoff">Lunasin</button>
                        </div>
                    @endunless
                    <label>Filter <input type="search" placeholder="Cari nama, pinjaman..." data-table-search></label>
                    <label>Tampilkan
                        <select data-page-size>
                            <option value="auto" @selected(request()->query('per_page') !== 'all')>Otomatis</option>
                            <option value="all" @selected(request()->query('per_page') === 'all')>Semua</option>
                        </select>
                    </label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan">
                        <button type="button" data-view-mode="table" aria-label="Tampilan tabel" title="Tabel">▤</button>
                        <button type="button" class="is-active" data-view-mode="cards" aria-label="Tampilan kartu" title="Kartu">▦</button>
                    </div>
                </div>
                <div class="overflow-x-auto dk-table-view dk-payments-table-wrap" data-table-view="table">
                    <table class="w-full text-left text-sm dk-payments-table">
                        <thead><tr><th>Waktu</th><th>Nasabah</th><th>Pinjaman</th><th>Nominal</th><th>Jenis</th><th>Status</th><th>Referensi / Bukti</th></tr></thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr data-table-row>
                                    <td class="dk-time-cell"><span>{{ $payment->created_at->format('H:i') }}</span><small>{{ $payment->created_at->format('d/m/Y') }}</small></td>
                                    <td>{{ $payment->loan->borrower->full_name }}</td>
                                    <td>{{ $payment->installment ? 'Cicilan ke-'.$payment->installment->sequence : 'Pembayaran' }}</td>
                                    <td class="font-bold">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                                    <td>{{ $payment->payment_method === 'transfer' ? 'Transfer' : 'Tunai' }}</td>
                                    <td><span class="dk-status" data-status="{{ $payment->status }}">{{ match ($payment->status) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => ucfirst($payment->status) } }}</span></td>
                                    <td class="dk-table-actions">
                                        <div class="dk-payment-table-actions">
                                        @if ($payment->proof_path)
                                            <a class="dk-inline-action" href="{{ route('cooperative.payments.proof', $payment) }}" target="_blank" rel="noopener">Lihat bukti</a>
                                        @endif
                                        @if ($payment->status === 'approved')
                                            <span class="dk-payment-reference">Ref. DKP-{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</span>
                                        @endif
                                        @if (auth()->user()->isManager() && $payment->status !== 'rejected')
                                            <button type="button" class="dk-inline-action" data-payment-edit data-url="{{ route('cooperative.payments.update', $payment) }}" data-amount="{{ $payment->amount }}" data-loan-id="{{ $payment->loan_id }}" data-installment-id="{{ $payment->installment_id }}">Koreksi</button>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-8 text-center text-slate-500">Belum ada pembayaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($payments as $payment)
                        <article class="dk-payment-card" data-table-row>
                            <div><strong>{{ $payment->loan->borrower->full_name }}</strong><span class="dk-status" data-status="{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></div>
                            <p>{{ $payment->installment ? 'Cicilan ke-'.$payment->installment->sequence : 'Pembayaran' }} · {{ $payment->loan->loan_code }}</p>
                            <b>Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</b>
                            <small>{{ $payment->created_at->format('d/m/Y H:i') }} · {{ $payment->payment_method === 'transfer' ? 'Transfer' : 'Tunai' }}</small>
                            @if ($payment->status === 'approved')
                                <div class="dk-payment-receipt">
                                    <strong>Ref. DKP-{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</strong>
                                </div>
                            @endif
                            @if ($payment->proof_path)<a class="dk-inline-action" href="{{ route('cooperative.payments.proof', $payment) }}" target="_blank" rel="noopener">Lihat bukti transfer</a>@endif
                            @if (auth()->user()->isManager() && $payment->status !== 'rejected')<button type="button" class="dk-inline-action w-fit" data-payment-edit data-url="{{ route('cooperative.payments.update', $payment) }}" data-amount="{{ $payment->amount }}" data-loan-id="{{ $payment->loan_id }}" data-installment-id="{{ $payment->installment_id }}">Koreksi setoran</button>@endif
                        </article>
                    @endforeach
                </div>
                @if ($payments instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="dk-table-footer">{{ $payments->links() }}</div>
                @endif
                @if (auth()->user()->isManager())
                    <dialog class="dk-password-native-dialog" data-payment-edit-dialog>
                        <form method="POST" class="dk-password-form">
                            @csrf
                            @method('PATCH')
                            <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                            <p class="dk-eyebrow">KOREKSI SETORAN</p>
                            <h3>Koreksi setoran</h3>
                            <label>Nasabah / kontrak
                                <select name="loan_id" required data-edit-loan-select>
                                    @foreach ($correctionLoans as $loan)
                                        <option value="{{ $loan->id }}" data-installments="{{ $loan->installments->map(fn ($installment) => ['id' => $installment->id, 'sequence' => $installment->sequence, 'amount' => $installment->outstanding_amount])->values()->toJson() }}">{{ $loan->borrower->full_name }} · {{ $loan->loan_code }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Cicilan
                                <select name="installment_id" required data-edit-installment-select></select>
                            </label>
                            <label>Nominal yang benar (Rp)<input type="text" name="amount" required inputmode="numeric" data-format-rupiah data-min="1"></label>
                            <p class="dk-modal-note">Nasabah dan kontrak mengikuti pilihan di atas. Saldo kas, cicilan, bonus, dan uang muka akan dihitung ulang.</p>
                            <button type="submit" class="dk-button-primary">Simpan koreksi</button>
                        </form>
                    </dialog>
                @endif
            </section>

            @unless (auth()->user()->isBorrower())
                <dialog class="dk-password-native-dialog dk-payment-create-dialog" id="payment-create-dialog" @if ($errors->any() && old('_form') === 'payment-create') data-open-on-error @endif>
                    <form method="POST" action="{{ route('cooperative.payments.store') }}" enctype="multipart/form-data" class="dk-password-form" data-payment-form>
                    @csrf
                    <input type="hidden" name="_form" value="payment-create">
                    <input type="hidden" name="payment_type" value="{{ old('payment_type', 'installment') }}" data-payment-type>
                    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                    <p class="dk-eyebrow">PEMBAYARAN</p>
                    <h3 data-payment-title>Catat setoran</h3>
                    <p class="dk-modal-note">{{ auth()->user()->isManager() ? 'Setoran Owner langsung disetujui.' : 'Setoran Admin menunggu persetujuan Owner.' }}</p>
                    <div class="dk-payment-form-grid">
                        <label class="dk-payment-loan-field" for="payment-loan">Pinjaman
                            <select id="payment-loan" name="loan_id" required data-loan-select>
                                @forelse ($paymentLoans as $loan)
                                    <option value="{{ $loan->id }}" data-total-outstanding="{{ $loan->installments->sum('outstanding_amount') }}" data-installments="{{ $loan->installments->map(fn ($installment) => ['id' => $installment->id, 'sequence' => $installment->sequence, 'amount' => $installment->outstanding_amount])->values()->toJson() }}" @selected(old('loan_id') == $loan->id)>
                                        {{ $loan->borrower->full_name }} · {{ $loan->loan_code }}
                                    </option>
                                @empty
                                    <option value="" data-total-outstanding="0" data-installments="[]">Tidak ada pinjaman aktif dengan cicilan tersisa</option>
                                @endforelse
                            </select>
                        </label>
                        <label data-installment-field for="payment-installment">Cicilan yang dibayar
                            <select id="payment-installment" name="installment_id" required data-installment-select>
                                <option value="">Pilih cicilan</option>
                            </select>
                        </label>
                        <label data-nominal-field for="nominal-source">Jenis nominal
                            <select id="nominal-source" name="nominal_source" required data-nominal-source>
                                <option value="system">Nominal sistem</option><option value="manual" @selected(old('nominal_source') === 'manual')>Nominal manual</option>
                            </select>
                        </label>
                        <div class="dk-system-installment dk-payment-wide-field" data-system-installment>
                            <span>SETORAN OTOMATIS</span>
                            <strong data-installment-label>Pilih cicilan</strong>
                            <b data-installment-amount>Rp 0</b>
                        </div>
                        <div class="hidden dk-payment-wide-field" data-manual-amount>
                            <label for="payment-amount">Nominal diterima (Rp)
                                <input id="payment-amount" name="amount" type="text" inputmode="numeric" data-format-rupiah data-min="1" value="{{ old('amount') }}" disabled>
                            </label>
                        </div>
                        <div class="dk-system-installment dk-payment-wide-field hidden" data-payoff-summary>
                            <span>TOTAL PELUNASAN</span>
                            <strong>Sisa seluruh cicilan</strong>
                            <b data-payoff-amount>Rp 0</b>
                        </div>
                        <label class="dk-payment-method-field" for="payment-method">Jenis pembayaran
                            <select id="payment-method" name="payment_method" required data-payment-method>
                                <option value="cash">Tunai</option><option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                            </select>
                        </label>
                    </div>
                    <div class="dk-transfer-proof hidden" data-transfer-proof>
                        <label>Bukti transfer</label>
                        <div class="dk-proof-actions">
                            <label class="dk-file-button">Pilih Foto<input type="file" accept="image/*" class="dk-proof-source" aria-label="Pilih bukti transfer"></label>
                            <label class="dk-file-button">Ambil Foto<input type="file" accept="image/*" capture="environment" class="dk-proof-source" aria-label="Foto bukti transfer"></label>
                        </div>
                        <input type="file" name="proof" class="dk-proof-final" accept="image/webp" hidden>
                        <p data-proof-status>Gambar dikompres otomatis ke WebP sebelum diunggah.</p>
                        <img class="dk-proof-preview hidden" alt="Pratinjau bukti transfer" data-proof-preview>
                    </div>
                    <button class="dk-button-primary" data-payment-submit>Catat Pembayaran</button>
                    </form>
                </dialog>
            @endunless
        </div>
    </div>
</x-app-layout>
