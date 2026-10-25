<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Riwayat Keuangan</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
                <form method="POST" action="{{ route('cooperative.finances.store') }}" class="h-fit rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="font-bold text-slate-900">Catat Perubahan Kas</h3>
                    <p class="mt-2 text-sm text-slate-600">Gunakan untuk saldo modal awal atau transaksi kas manual yang memiliki bukti.</p>
                    @if ($errors->any())<p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p>@endif
                    @if (session('status'))<p class="mt-3 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                    <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Arah transaksi</label>
                    <select name="direction" required class="mt-2 w-full rounded-md border-slate-300">
                        <option value="in">Kas masuk</option><option value="out">Kas keluar</option>
                    </select>
                    <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Nominal (Rp)</label>
                    <input name="amount" type="number" min="1" required value="{{ old('amount') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Keterangan / referensi</label>
                    <input name="description" maxlength="255" required value="{{ old('description') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <button class="mt-5 w-full rounded-md bg-blue-600 px-4 py-3 font-bold text-white">Catat Transaksi</button>
                </form>

                <section class="rounded-lg border border-sky-200 bg-sky-100 p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase text-slate-600">Saldo kas saat ini</p>
                    <p class="mt-3 text-3xl font-extrabold text-slate-950">Rp {{ number_format($balance, 0, ',', '.') }}</p>
                    <p class="mt-3 text-sm text-slate-600">Saldo dihitung berurutan dari transaksi kas yang dicatat.</p>
                </section>
            </div>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Keterangan</th><th class="px-5 py-3">Kas masuk</th><th class="px-5 py-3">Kas keluar</th><th class="px-5 py-3">Saldo</th><th class="px-5 py-3">Petugas</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 py-3">{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-3">{{ str_replace('_', ' ', $transaction->type) }}</td>
                                    <td class="px-5 py-3">{{ $transaction->description }} @if ($transaction->loan) ({{ $transaction->loan->loan_code }}) @endif</td>
                                    <td class="px-5 py-3">{{ $transaction->direction === 'in' ? 'Rp '.number_format((int) $transaction->amount, 0, ',', '.') : '—' }}</td>
                                    <td class="px-5 py-3">{{ $transaction->direction === 'out' ? 'Rp '.number_format((int) $transaction->amount, 0, ',', '.') : '—' }}</td>
                                    <td class="px-5 py-3 font-semibold">Rp {{ number_format((int) $transaction->balance_after, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3">{{ $transaction->creator->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Belum ada transaksi keuangan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-3">{{ $transactions->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
