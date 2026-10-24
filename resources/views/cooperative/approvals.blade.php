<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Approval Pembayaran</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-6xl rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="font-bold text-slate-900">Antrean Menunggu Manager</h3>
                @if (session('status'))<p class="mt-2 text-sm text-emerald-700">{{ session('status') }}</p>@endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Penginput</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Nominal</th><th class="px-5 py-3 text-right">Tindakan</th></tr></thead>
                    <tbody>
                    @forelse ($approvalRequests as $approval)
                        @php($payment = $approval->approvable)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 py-4">{{ $approval->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">{{ $approval->requester->name }}</td>
                            <td class="px-5 py-4">{{ $approval->request_type }}</td>
                            <td class="px-5 py-4 font-bold">{{ $payment ? 'Rp '.number_format($payment->amount,0,',','.') : '-' }}</td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-2">
                                @if ($payment)
                                <form method="POST" action="{{ route('cooperative.payments.reject', $payment) }}">@csrf<button class="rounded-md bg-red-100 px-3 py-2 text-xs font-bold text-red-700">Tolak</button></form>
                                <form method="POST" action="{{ route('cooperative.payments.approve', $payment) }}">@csrf<button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Approve</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Tidak ada antrean pembayaran.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
