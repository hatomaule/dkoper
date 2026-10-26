<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Antrean Persetujuan</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="dk-data-panel mx-auto max-w-6xl" data-table-tools>
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="font-bold text-slate-900">Menunggu keputusan Owner</h3>
                @if (session('status'))<p class="mt-2 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                @if ($errors->any())<p class="mt-2 text-sm text-red-600">{{ $errors->first() }}</p>@endif
            </div>
            <div class="dk-table-toolbar">
                <label>Filter <input type="search" placeholder="Cari pengajuan..." data-table-search></label>
                <label>Tampilkan<select data-page-size><option value="5" @selected($perPage === 5)>5</option><option value="10" @selected($perPage === 10)>10</option><option value="25" @selected($perPage === 25)>25</option><option value="50" @selected($perPage === 50)>50</option><option value="all" @selected($perPage === 'all')>Semua</option></select></label>
                <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
            </div>
            <div class="overflow-x-auto dk-table-view" data-table-view="table">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Penginput</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Detail</th><th class="px-5 py-3 text-right">Tindakan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($approvalRequests as $approval)
                            @php($record = $approval->approvable)
                            <tr class="border-t border-slate-100" data-table-row>
                                <td class="dk-time-cell"><span>{{ $approval->created_at->format('H:i') }}</span><small>{{ $approval->created_at->format('d/m/Y') }}</small></td>
                                <td class="px-5 py-4">{{ $approval->requester->name }}</td>
                                <td class="px-5 py-4">{{ $approval->request_type === 'loan' ? 'Pinjaman' : 'Pembayaran' }}</td>
                                <td class="px-5 py-4">
                                    @if ($record && $approval->request_type === 'loan')
                                        {{ $record->loan_code }} — {{ $record->borrower->full_name }}<br>
                                        Pokok Rp {{ number_format((int) $record->principal_amount, 0, ',', '.') }}
                                    @elseif ($record)
                                        {{ $record->loan->loan_code }} — {{ $record->loan->borrower->full_name }}<br>
                                        Setoran Rp {{ number_format((int) $record->amount, 0, ',', '.') }}
                                        @if ($record->proof_path)
                                            <br><a class="dk-inline-action" href="{{ route('cooperative.payments.proof', $record) }}" target="_blank" rel="noopener">Lihat bukti transfer</a>
                                        @endif
                                    @else
                                        Data pengajuan tidak tersedia.
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($record)
                                        <div class="flex justify-end gap-2">
                                            @if ($approval->request_type === 'loan')
                                                <button type="button" class="rounded-md bg-red-100 px-3 py-2 text-xs font-bold text-red-700" data-open-dialog="#reject-loan-{{ $record->id }}">Tolak</button>
                                                <form method="POST" action="{{ route('cooperative.loans.approve', $record) }}">@csrf<button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Setujui</button></form>
                                            @else
                                                <button type="button" class="rounded-md bg-red-100 px-3 py-2 text-xs font-bold text-red-700" data-open-dialog="#reject-payment-{{ $record->id }}">Tolak</button>
                                                <form method="POST" action="{{ route('cooperative.payments.approve', $record) }}">@csrf<button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Setujui</button></form>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Tidak ada antrean persetujuan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                @foreach ($approvalRequests as $approval)
                    @php($record = $approval->approvable)
                    <article class="dk-payment-card" data-table-row>
                        <div>
                            <strong>{{ $approval->request_type === 'loan' ? 'Pengajuan pinjaman' : 'Pengajuan setoran' }}</strong>
                            <span>{{ $approval->created_at->format('H:i · d/m/Y') }}</span>
                        </div>
                        <p>Oleh {{ $approval->requester->name }}</p>
                        @if ($record && $approval->request_type === 'loan')
                            <p>{{ $record->loan_code }} · {{ $record->borrower->full_name }}</p>
                            <b>Pokok Rp {{ number_format((int) $record->principal_amount, 0, ',', '.') }}</b>
                        @elseif ($record)
                            <p>{{ $record->loan->loan_code }} · {{ $record->loan->borrower->full_name }}</p>
                            <b>Setoran Rp {{ number_format((int) $record->amount, 0, ',', '.') }}</b>
                            @if ($record->proof_path)
                                <a class="dk-inline-action" href="{{ route('cooperative.payments.proof', $record) }}" target="_blank" rel="noopener">Lihat bukti transfer</a>
                            @endif
                        @else
                            <small>Data pengajuan tidak tersedia.</small>
                        @endif
                        @if ($record)
                            <div class="flex gap-2">
                                @if ($approval->request_type === 'loan')
                                    <button type="button" class="dk-button-secondary" data-open-dialog="#reject-loan-{{ $record->id }}">Tolak</button>
                                    <form method="POST" action="{{ route('cooperative.loans.approve', $record) }}">@csrf<button class="dk-button-primary">Setujui</button></form>
                                @else
                                    <button type="button" class="dk-button-secondary" data-open-dialog="#reject-payment-{{ $record->id }}">Tolak</button>
                                    <form method="POST" action="{{ route('cooperative.payments.approve', $record) }}">@csrf<button class="dk-button-primary">Setujui</button></form>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
            @if ($approvalRequests instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="dk-table-footer">{{ $approvalRequests->links() }}</div>
            @endif
        </div>
    </div>

    @foreach ($approvalRequests as $approval)
        @php($record = $approval->approvable)
        @if ($record && $approval->request_type === 'loan')
            <dialog class="dk-password-native-dialog" id="reject-loan-{{ $record->id }}">
                <form method="POST" action="{{ route('cooperative.loans.reject', $record) }}" class="dk-password-form">
                    @csrf
                    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                    <p class="dk-eyebrow">KONFIRMASI</p>
                    <h3>Tolak pengajuan pinjaman?</h3>
                    <label>Alasan penolakan<textarea name="reason" rows="3" minlength="3" maxlength="1000" required placeholder="Tulis alasan penolakan..."></textarea></label>
                    <div class="flex gap-2">
                        <button type="button" class="dk-button-secondary" data-close-dialog>Batal</button>
                        <button type="submit" class="dk-button-primary">Tolak</button>
                    </div>
                </form>
            </dialog>
        @elseif ($record && $approval->request_type !== 'loan')
            <dialog class="dk-password-native-dialog" id="reject-payment-{{ $record->id }}">
                <form method="POST" action="{{ route('cooperative.payments.reject', $record) }}" class="dk-password-form">
                    @csrf
                    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                    <p class="dk-eyebrow">KONFIRMASI</p>
                    <h3>Tolak pembayaran ini?</h3>
                    <label>Alasan penolakan<textarea name="reason" rows="3" minlength="3" maxlength="1000" required placeholder="Tulis alasan penolakan..."></textarea></label>
                    <div class="flex gap-2">
                        <button type="button" class="dk-button-secondary" data-close-dialog>Batal</button>
                        <button type="submit" class="dk-button-primary">Tolak</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endforeach
</x-app-layout>
