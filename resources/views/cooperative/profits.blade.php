<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Bonus dan Penarikan Staf</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl space-y-6">
            @if (session('status'))<p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif

            <section class="grid gap-4 md:grid-cols-2">
                @foreach ($balances as $balance)
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="font-bold text-slate-900">{{ $balance->user->name }} <span class="text-xs font-normal uppercase text-slate-500">{{ $balance->user->role }}</span></h3>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-500">Bonus didapat</dt><dd class="font-semibold">Rp {{ number_format((int) $balance->earned_amount, 0, ',', '.') }}</dd></div>
                            <div><dt class="text-slate-500">Sudah ditarik</dt><dd class="font-semibold">Rp {{ number_format((int) $balance->withdrawn_amount, 0, ',', '.') }}</dd></div>
                            <div><dt class="text-slate-500">Dipindah ke modal</dt><dd class="font-semibold">Rp {{ number_format((int) $balance->transferred_to_modal_amount, 0, ',', '.') }}</dd></div>
                            <div><dt class="text-slate-500">Saldo tersedia / utang bonus</dt><dd class="font-bold {{ $balance->available_amount < 0 ? 'text-red-700' : 'text-emerald-700' }}">Rp {{ number_format((int) $balance->available_amount, 0, ',', '.') }}</dd></div>
                        </dl>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <form method="POST" action="{{ route('cooperative.profits.withdraw', $balance->user) }}">
                                @csrf<label class="block text-xs font-bold uppercase text-slate-600">Catat penarikan</label>
                                <input name="amount" type="number" min="1" required class="mt-2 w-full rounded-md border-slate-300" placeholder="Nominal Rp">
                                <button class="mt-2 w-full rounded-md bg-orange-600 px-3 py-2 text-sm font-bold text-white">Tarik</button>
                            </form>
                            <form method="POST" action="{{ route('cooperative.profits.transfer', $balance->user) }}">
                                @csrf<label class="block text-xs font-bold uppercase text-slate-600">Transfer ke modal</label>
                                <input name="amount" type="number" min="1" max="{{ max(0, (int) $balance->available_amount) }}" required class="mt-2 w-full rounded-md border-slate-300" placeholder="Nominal Rp">
                                <button class="mt-2 w-full rounded-md bg-blue-600 px-3 py-2 text-sm font-bold text-white">Transfer</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-bold text-slate-900">Riwayat Bonus</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Staf</th><th class="px-5 py-3">Jenis</th><th class="px-5 py-3">Nominal</th><th class="px-5 py-3">Saldo setelah</th><th class="px-5 py-3">Dicatat oleh</th><th class="px-5 py-3">Keterangan</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                <tr class="border-t border-slate-100"><td class="px-5 py-3">{{ $transaction->created_at->format('d/m/Y H:i') }}</td><td class="px-5 py-3">{{ $transaction->user->name }}</td><td class="px-5 py-3">{{ str_replace('_', ' ', $transaction->type) }}</td><td class="px-5 py-3">Rp {{ number_format((int) $transaction->amount, 0, ',', '.') }}</td><td class="px-5 py-3">Rp {{ number_format((int) $transaction->balance_after, 0, ',', '.') }}</td><td class="px-5 py-3">{{ $transaction->creator->name }}</td><td class="px-5 py-3">{{ $transaction->description }}</td></tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Belum ada transaksi bonus.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-3">{{ $transactions->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
