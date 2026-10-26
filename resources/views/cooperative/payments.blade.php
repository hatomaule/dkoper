<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pembayaran</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto grid max-w-7xl items-stretch gap-5 lg:grid-cols-[minmax(240px,290px)_minmax(0,1fr)]">
            <form method="POST" action="{{ route('cooperative.payments.store') }}" enctype="multipart/form-data" class="dk-form-panel self-start" data-payment-form>
                @csrf
                <h3 class="font-bold text-slate-900">Catat Setoran</h3>
                <p class="mt-1 text-xs text-slate-500">{{ auth()->user()->isManager() ? 'Setoran Owner langsung disetujui.' : 'Setoran Admin menunggu persetujuan Owner.' }}</p>
                @if ($errors->any())<div class="dk-form-alert">{{ $errors->first() }}</div>@endif
                @if (session('status'))<div class="dk-form-success">{{ session('status') }}</div>@endif
                <label class="mt-5 block text-xs font-bold uppercase text-slate-600" for="payment-loan">Pinjaman</label>
                <select id="payment-loan" name="loan_id" required class="mt-2 w-full" data-loan-select>
                    @forelse ($paymentLoans as $loan)
                        <option value="{{ $loan->id }}" data-installments="{{ $loan->installments->map(fn ($installment) => ['id' => $installment->id, 'sequence' => $installment->sequence, 'amount' => $installment->outstanding_amount])->values()->toJson() }}">
                            {{ $loan->borrower->full_name }} · {{ $loan->loan_code }}
                        </option>
                    @empty
                        <option value="" data-installments="[]">Tidak ada pinjaman aktif dengan cicilan tersisa</option>
                    @endforelse
                </select>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600" for="payment-installment">Cicilan yang dibayar</label>
                <select id="payment-installment" name="installment_id" required class="mt-2 w-full" data-installment-select>
                    <option value="">Pilih cicilan</option>
                </select>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600" for="nominal-source">Jenis Nominal</label>
                <select id="nominal-source" name="nominal_source" required class="mt-2 w-full" data-nominal-source>
                    <option value="system">Nominal Sistem</option><option value="manual">Nominal Manual</option>
                </select>
                <div class="dk-system-installment mt-3" data-system-installment>
                    <span>SETORAN OTOMATIS</span>
                    <strong data-installment-label>Pilih cicilan</strong>
                    <b data-installment-amount>Rp 0</b>
                </div>
                <div class="mt-4 hidden" data-manual-amount>
                    <label class="block text-xs font-bold uppercase text-slate-600" for="payment-amount">Nominal Diterima (Rp)</label>
                    <input id="payment-amount" name="amount" type="text" inputmode="numeric" data-format-rupiah data-min="1" class="mt-2 w-full" disabled>
                </div>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600" for="payment-method">Jenis Pembayaran</label>
                <select id="payment-method" name="payment_method" required class="mt-2 w-full" data-payment-method>
                    <option value="cash">Tunai</option><option value="transfer">Transfer</option>
                </select>
                <div class="dk-transfer-proof mt-4 hidden" data-transfer-proof>
                    <label class="block text-xs font-bold uppercase text-slate-600">Bukti Transfer</label>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label class="dk-file-button">Pilih Foto<input type="file" accept="image/*" class="dk-proof-source" aria-label="Pilih bukti transfer"></label>
                        <label class="dk-file-button">Ambil Foto<input type="file" accept="image/*" capture="environment" class="dk-proof-source" aria-label="Foto bukti transfer"></label>
                    </div>
                    <input type="file" name="proof" class="dk-proof-final" accept="image/webp" hidden>
                    <p class="mt-2 text-xs text-slate-500" data-proof-status>Gambar dikompres otomatis ke WebP sebelum diunggah.</p>
                    <img class="dk-proof-preview mt-3 hidden" alt="Pratinjau bukti transfer" data-proof-preview>
                </div>
                <button class="dk-button-primary mt-5 w-fit px-4 py-2 font-bold">Catat</button>
            </form>

            <section class="dk-data-panel min-w-0" data-table-tools>
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari nama, pinjaman..." data-table-search></label>
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
                        <button type="button" class="is-active" data-view-mode="table" aria-label="Tampilan tabel" title="Tabel">▤</button>
                        <button type="button" data-view-mode="cards" aria-label="Tampilan kartu" title="Kartu">▦</button>
                    </div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th>Waktu</th><th>Nasabah</th><th>Pinjaman</th><th>Nominal</th><th>Jenis</th><th>Status</th><th>Bukti / Kirim</th></tr></thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                @php
                                    $receipt = "BUKTI SETORAN DKOPER\n------------------------------\nNasabah : {$payment->loan->borrower->full_name}\nPinjaman: {$payment->loan->loan_code}\nTanggal : {$payment->created_at->format('d/m/Y H:i')}\nNominal : Rp ".number_format((int) $payment->amount, 0, ',', '.')."\nJenis   : ".($payment->payment_method === 'transfer' ? 'Transfer' : 'Tunai')."\nStatus  : ".($payment->status === 'approved' ? 'Disetujui' : ($payment->status === 'pending' ? 'Menunggu' : 'Ditolak'))."\n------------------------------\nTerima kasih.";
                                    $phone = preg_replace('/\D+/', '', (string) $payment->loan->borrower->phone);
                                    $phone = str_starts_with($phone, '0') ? '62'.substr($phone, 1) : (str_starts_with($phone, '62') ? $phone : '62'.$phone);
                                    if (strlen($phone) < 10 || strlen($phone) > 15) $phone = '';
                                @endphp
                                <tr data-table-row>
                                    <td class="dk-time-cell"><span>{{ $payment->created_at->format('H:i') }}</span><small>{{ $payment->created_at->format('d/m/Y') }}</small></td>
                                    <td>{{ $payment->loan->borrower->full_name }}</td>
                                    <td>{{ $payment->installment ? 'Cicilan ke-'.$payment->installment->sequence : 'Pembayaran' }}</td>
                                    <td class="font-bold">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                                    <td>{{ $payment->payment_method === 'transfer' ? 'Transfer' : 'Tunai' }}</td>
                                    <td><span class="dk-status" data-status="{{ $payment->status }}">{{ match ($payment->status) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => ucfirst($payment->status) } }}</span></td>
                                    <td class="dk-table-actions">
                                        @if ($payment->proof_path)
                                            <a class="dk-inline-action" href="{{ route('cooperative.payments.proof', $payment) }}" target="_blank" rel="noopener">Lihat bukti</a>
                                        @endif
                                        @if ($payment->status === 'approved' && $phone !== '')
                                            <a class="dk-inline-action" href="https://wa.me/{{ $phone }}?text={{ rawurlencode($receipt) }}" target="_blank" rel="noopener">Kirim WA</a>
                                        @elseif ($payment->status === 'approved')
                                            <span class="text-xs text-slate-400" title="Nomor WA belum tersedia">WA tidak tersedia</span>
                                        @endif
                                        @if (auth()->user()->isManager() && $payment->status !== 'rejected')
                                            <button type="button" class="dk-inline-action" data-payment-edit data-url="{{ route('cooperative.payments.update', $payment) }}" data-amount="{{ $payment->amount }}" data-loan-id="{{ $payment->loan_id }}" data-installment-id="{{ $payment->installment_id }}">Koreksi</button>
                                        @endif
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
                        @php
                            $cardPhone = preg_replace('/\D+/', '', (string) $payment->loan->borrower->phone);
                            $cardPhone = str_starts_with($cardPhone, '0') ? '62'.substr($cardPhone, 1) : (str_starts_with($cardPhone, '62') ? $cardPhone : '62'.$cardPhone);
                            if (strlen($cardPhone) < 10 || strlen($cardPhone) > 15) $cardPhone = '';
                            $cardReceipt = "BUKTI SETORAN DKOPER\n------------------------------\nNasabah : {$payment->loan->borrower->full_name}\nNominal : Rp ".number_format((int) $payment->amount, 0, ',', '.')."\nStatus  : ".($payment->status === 'approved' ? 'Disetujui' : ($payment->status === 'pending' ? 'Menunggu' : 'Ditolak'))."\n------------------------------\nTerima kasih.";
                        @endphp
                        <article class="dk-payment-card" data-table-row>
                            <div><strong>{{ $payment->loan->borrower->full_name }}</strong><span class="dk-status" data-status="{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></div>
                            <p>{{ $payment->installment ? 'Cicilan ke-'.$payment->installment->sequence : 'Pembayaran' }}</p>
                            <b>Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</b>
                            <small>{{ $payment->created_at->format('d/m/Y H:i') }} · {{ $payment->payment_method === 'transfer' ? 'Transfer' : 'Tunai' }}</small>
                            @if ($payment->proof_path)<a class="dk-inline-action" href="{{ route('cooperative.payments.proof', $payment) }}" target="_blank" rel="noopener">Lihat bukti transfer</a>@endif
                            @if ($payment->status === 'approved' && $cardPhone !== '')<a class="dk-inline-action" href="https://wa.me/{{ $cardPhone }}?text={{ rawurlencode($cardReceipt) }}" target="_blank" rel="noopener">Kirim bukti via WA</a>@endif
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
        </div>
    </div>
</x-app-layout>
