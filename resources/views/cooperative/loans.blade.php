<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pinjaman</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-6xl grid gap-6 lg:grid-cols-[360px_1fr]">
            <form method="POST" action="{{ route('cooperative.loans.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm h-fit">
                @csrf
                <h3 class="font-bold text-slate-900">Ajukan Pinjaman</h3>
                <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Nasabah</label>
                <select name="borrower_id" required class="mt-2 w-full rounded-md border-slate-300">@foreach ($borrowers as $borrower)<option value="{{ $borrower->id }}">{{ $borrower->borrower_code }} — {{ $borrower->full_name }}</option>@endforeach</select>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Pokok Pinjaman</label>
                <input name="principal_amount" type="number" min="1000" required class="mt-2 w-full rounded-md border-slate-300" placeholder="1000000">
                <button class="mt-5 w-full rounded-md bg-blue-600 px-4 py-3 font-bold text-white">{{ auth()->user()->isManager() ? 'Setujui & Catat' : 'Kirim Pengajuan' }}</button>
                @if (session('status'))<div class="mt-4 text-sm text-emerald-700">{{ session('status') }}</div>@endif
            </form>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Nasabah</th><th class="px-5 py-3">Pokok</th><th class="px-5 py-3">Tagihan</th><th class="px-5 py-3">Status</th></tr></thead><tbody>@foreach ($loans as $loan)<tr class="border-t border-slate-100"><td class="px-5 py-3 font-bold">{{ $loan->loan_code }}</td><td class="px-5 py-3">{{ $loan->borrower->full_name }}</td><td class="px-5 py-3">Rp {{ number_format($loan->principal_amount,0,',','.') }}</td><td class="px-5 py-3">Rp {{ number_format($loan->total_receivable,0,',','.') }}</td><td class="px-5 py-3">{{ $loan->status }}</td></tr>@endforeach</tbody></table></div>
        </div>
    </div>
</x-app-layout>
