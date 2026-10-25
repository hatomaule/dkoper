<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pembayaran</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-6xl grid gap-6 lg:grid-cols-[360px_1fr]">
            <form method="POST" action="{{ route('cooperative.payments.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm h-fit">
                @csrf
                <h3 class="font-bold text-slate-900">Catat Setoran</h3>
                @if (session('status'))<div class="mt-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="mt-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
                <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Pinjaman</label>
                <select name="loan_id" required class="mt-2 w-full rounded-md border-slate-300" onchange="location.hash=this.value">@foreach ($loans as $loan)<option value="{{ $loan->id }}">{{ $loan->loan_code }} — {{ $loan->borrower->full_name }}</option>@endforeach</select>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Jenis Nominal</label>
                <select name="nominal_source" required class="mt-2 w-full rounded-md border-slate-300"><option value="system">Nominal Sistem</option><option value="manual">Nominal Manual</option></select>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Nominal Diterima</label>
                <input name="amount" type="number" min="1" required class="mt-2 w-full rounded-md border-slate-300">
                <button class="mt-5 w-full rounded-md bg-emerald-600 px-4 py-3 font-bold text-white">{{ auth()->user()->isManager() ? 'Catat & Setujui' : 'Kirim ke Approval' }}</button>
                @if (session('status'))<div class="mt-4 text-sm text-emerald-700">{{ session('status') }}</div>@endif
            </form>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Nasabah</th><th class="px-5 py-3">Nominal</th><th class="px-5 py-3">Status</th></tr></thead><tbody>@forelse ($payments as $payment)<tr class="border-t border-slate-100"><td class="px-5 py-3">{{ $payment->created_at->format('d/m/Y H:i') }}</td><td class="px-5 py-3">{{ $payment->loan->borrower->full_name }}</td><td class="px-5 py-3 font-bold">Rp {{ number_format($payment->amount,0,',','.') }}</td><td class="px-5 py-3"><span class="dk-status" data-status="{{ $payment->status }}">{{ match ($payment->status) { 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => ucfirst($payment->status) } }}</span></td></tr>@empty<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Belum ada pembayaran.</td></tr>@endforelse</tbody></table>{{ $payments->links() }}</div>
        </div>
    </div>
</x-app-layout>
