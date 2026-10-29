<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Bonus</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl space-y-6">
            @if (session('status'))<p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif

            @if (auth()->user()->isManager())
                <section class="dk-profit-balances grid gap-4">
                    @foreach ($balances as $balance)
                        <div class="dk-form-panel dk-profit-card {{ $balance->user->isManager() ? 'dk-bonus-owner' : 'dk-bonus-admin' }}">
                        <h3 class="font-bold text-slate-900">
                            {{ $balance->user->name }}
                            @if (mb_strtolower(trim($balance->user->name)) !== mb_strtolower($balance->user->isManager() ? 'Owner' : 'Admin'))
                                <span class="text-xs font-normal uppercase text-slate-500">{{ $balance->user->isManager() ? 'OWNER' : 'ADMIN' }}</span>
                            @endif
                        </h3>
                        <dl class="dk-profit-stats mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-500">{{ $balance->user->isCashier() ? 'Bonus dari hasil pembayaran nasabah' : 'Bonus didapat' }}</dt><dd class="font-semibold">Rp {{ number_format((int) $balance->earned_amount, 0, ',', '.') }}</dd></div>
                            @if (auth()->user()->isManager())
                                <div><dt class="text-slate-500">Total bonus dari pinjaman aktif</dt><dd class="font-semibold">Rp {{ number_format((int) $balance->active_loan_bonus_amount, 0, ',', '.') }}</dd></div>
                            @endif
                            <div><dt class="text-slate-500">Sudah ditarik</dt><dd class="font-semibold">Rp {{ number_format((int) $balance->withdrawn_amount, 0, ',', '.') }}</dd></div>
                            @if ($balance->user->isManager())
                                <div><dt class="text-slate-500">Saldo tersedia</dt><dd class="font-bold {{ $balance->available_amount < 0 ? 'text-red-700' : 'text-emerald-700' }}">Rp {{ number_format((int) $balance->available_amount, 0, ',', '.') }}</dd></div>
                            @else
                                <div><dt class="text-red-700">Utang bonus</dt><dd class="font-bold text-red-700">Rp {{ number_format(min(0, (int) $balance->available_amount), 0, ',', '.') }}</dd></div>
                            @endif
                        </dl>
                        <div class="dk-profit-actions mt-5 grid gap-4 sm:grid-cols-2">
                            @if (auth()->user()->isManager())
                                <form method="POST" action="{{ route('cooperative.profits.withdraw', $balance->user) }}" class="dk-bonus-action-form">
                                    @csrf<label class="block text-xs font-bold uppercase text-slate-600">Catat penarikan {{ $balance->user->isManager() ? 'Owner' : 'Admin' }}</label>
                                    <input name="amount" type="text" inputmode="numeric" data-format-rupiah data-min="1" required value="{{ old('amount') }}" class="mt-2 w-full rounded-md border-slate-300" placeholder="Nominal Rp">
                                    <button class="dk-button-secondary dk-compact-action mt-2">Tarik</button>
                                </form>
                            @endif
                        </div>
                        </div>
                    @endforeach
                </section>
            @else
                @php($adminBalance = $balances->first())
                @if ($adminBalance)
                    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['Bonus dari hasil pembayaran nasabah', (int) $adminBalance->earned_amount],
                            ['Sudah ditarik', (int) $adminBalance->withdrawn_amount],
                            ['Utang bonus', min(0, (int) $adminBalance->available_amount)],
                        ] as [$label, $amount])
                            <article @class([
                                'dk-period-card',
                                'dk-admin-bonus-debt-card' => $label === 'Utang bonus',
                                'dk-admin-bonus-earned-card' => $label === 'Bonus dari hasil pembayaran nasabah',
                            ])>
                                <span @class(['text-red-700' => $label === 'Utang bonus'])>{{ $label }}</span>
                                <strong @class([
                                    'dk-admin-bonus-debt-value' => $label === 'Utang bonus' && $amount < 0,
                                    'dk-admin-bonus-earned-value' => $label === 'Bonus dari hasil pembayaran nasabah',
                                ])>Rp {{ number_format($amount, 0, ',', '.') }}</strong>
                            </article>
                        @endforeach
                    </section>
                @endif
            @endif

            <section class="dk-data-panel min-w-0" data-table-tools>
                <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-bold text-slate-900">Riwayat Bonus</h3></div>
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari transaksi..." data-table-search></label>
                    <label>Tampilkan<select data-page-size><option value="auto" @selected(request()->query('per_page') !== 'all')>Otomatis</option><option value="all" @selected(request()->query('per_page') === 'all')>Semua</option></select></label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" class="is-active" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Waktu</th><th class="px-5 py-3">Akun</th><th class="px-5 py-3">Aktivitas</th><th class="px-5 py-3">Nominal</th><th class="px-5 py-3">Saldo</th><th class="px-5 py-3">Dicatat oleh</th><th class="px-5 py-3">Keterangan</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $transaction)
                                @php($typeLabels = ['earned_interest' => 'Bonus cicilan lunas', 'withdrawal' => 'Penarikan', 'transfer_to_modal' => 'Dipindahkan ke modal', 'correction_reversal' => 'Koreksi bonus'])
                                <tr class="border-t border-slate-100" data-table-row>
                                    <td class="dk-time-cell"><span>{{ $transaction->created_at->format('H:i') }}</span><small>{{ $transaction->created_at->format('d/m/Y') }}</small></td>
                                    <td>{{ $transaction->user->name }}</td><td>{{ $typeLabels[$transaction->type] ?? 'Perubahan bonus' }}</td>
                                    <td class="font-semibold">Rp {{ number_format((int) $transaction->amount, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format((int) $transaction->balance_after, 0, ',', '.') }}</td>
                                    <td>{{ $transaction->creator->name }}</td><td>{{ $transaction->description }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Belum ada transaksi bonus.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($transactions as $transaction)
                        @php($typeLabels = ['earned_interest' => 'Bonus cicilan lunas', 'withdrawal' => 'Penarikan', 'transfer_to_modal' => 'Dipindahkan ke modal', 'correction_reversal' => 'Koreksi bonus'])
                        <article class="dk-payment-card" data-table-row>
                            <div><strong>{{ $transaction->user->name }}</strong><span>{{ $transaction->created_at->format('H:i · d/m/Y') }}</span></div>
                            <p>{{ $typeLabels[$transaction->type] ?? 'Perubahan bonus' }} · Dicatat oleh {{ $transaction->creator->name }}</p>
                            <b>Rp {{ number_format((int) $transaction->amount, 0, ',', '.') }}</b>
                            <small>Saldo setelah transaksi Rp {{ number_format((int) $transaction->balance_after, 0, ',', '.') }}</small>
                            <small>{{ $transaction->description }}</small>
                        </article>
                    @endforeach
                </div>
                @if ($transactions instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $transactions->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
