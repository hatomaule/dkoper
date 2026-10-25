<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Antrean Persetujuan</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-6xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="font-bold text-slate-900">Menunggu keputusan Manager</h3>
                @if (session('status'))<p class="mt-2 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                @if ($errors->any())<p class="mt-2 text-sm text-red-600">{{ $errors->first() }}</p>@endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Penginput</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Detail</th><th class="px-5 py-3 text-right">Tindakan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($approvalRequests as $approval)
                            @php($record = $approval->approvable)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 py-4">{{ $approval->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-4">{{ $approval->requester->name }}</td>
                                <td class="px-5 py-4">{{ $approval->request_type === 'loan' ? 'Pinjaman' : 'Pembayaran' }}</td>
                                <td class="px-5 py-4">
                                    @if ($record && $approval->request_type === 'loan')
                                        {{ $record->loan_code }} — {{ $record->borrower->full_name }}<br>
                                        Pokok Rp {{ number_format((int) $record->principal_amount, 0, ',', '.') }}
                                    @elseif ($record)
                                        {{ $record->loan->loan_code }} — {{ $record->loan->borrower->full_name }}<br>
                                        Setoran Rp {{ number_format((int) $record->amount, 0, ',', '.') }}
                                    @else
                                        Data pengajuan tidak tersedia.
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    @if ($record)
                                        <div class="flex justify-end gap-2">
                                            @if ($approval->request_type === 'loan')
                                                <form method="POST" action="{{ route('cooperative.loans.reject', $record) }}">@csrf<button class="rounded-md bg-red-100 px-3 py-2 text-xs font-bold text-red-700">Tolak</button></form>
                                                <form method="POST" action="{{ route('cooperative.loans.approve', $record) }}">@csrf<button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Setujui</button></form>
                                            @else
                                                <form method="POST" action="{{ route('cooperative.payments.reject', $record) }}">@csrf<button class="rounded-md bg-red-100 px-3 py-2 text-xs font-bold text-red-700">Tolak</button></form>
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
        </div>
    </div>
</x-app-layout>
