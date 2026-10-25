<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Bonus</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 py-8 px-4">
        <div class="mx-auto max-w-7xl space-y-6">
            @if (session('status'))<p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif

            <section class="grid gap-4 md:grid-cols-2">
                @foreach ($balances as $balance)
                    <div class="dk-form-panel dk-profit-card {{ $balance->user->isManager() ? 'dk-bonus-owner' : 'dk-bonus-admin' }}">
                        <h3 class="font-bold text-slate-900">{{ $balance->user->name }} <span class="text-xs font-normal uppercase text-slate-500">{{ $balance->user->isManager() ? 'OWNER' : 'ADMIN' }}</span></h3>
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
                                <button class="dk-button-secondary mt-2">Tarik</button>
                            </form>
                            @if ($balance->user->isManager())
                                <form method="POST" action="{{ route('cooperative.profits.transfer', $balance->user) }}">
                                    @csrf<label class="block text-xs font-bold uppercase text-slate-600">Transfer ke modal</label>
                                    <input name="amount" type="number" min="1" max="{{ max(0, (int) $balance->available_amount) }}" required class="mt-2 w-full rounded-md border-slate-300" placeholder="Nominal Rp">
                                    <button class="dk-button-secondary dk-button-blue mt-2">Transfer</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="dk-staff-password-section">
                <div class="dk-section-title"><div><span>KEAMANAN AKUN</span><h3>Kelola password Owner dan Admin</h3></div></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($accounts as $account)
                        <article class="dk-account-password-card">
                            <div><strong>{{ $account->name }}</strong><span>{{ $account->isManager() ? 'Owner' : 'Admin pengelola' }}</span></div>
                            <button type="button" class="dk-button-secondary" data-open-dialog="#staff-password-{{ $account->id }}">Ganti password</button>
                            <dialog class="dk-password-native-dialog" id="staff-password-{{ $account->id }}">
                                <form method="POST" action="{{ route('cooperative.staff.update-password', $account) }}" class="dk-password-form">
                                    @csrf
                                    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                                    <p class="dk-eyebrow">AKUN PENGELOLA</p>
                                    <h3>Ganti password {{ $account->name }}</h3>
                                    <label>Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                                    <label>Ulangi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                                    <button type="submit" class="dk-button-primary">Simpan password</button>
                                </form>
                            </dialog>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="dk-data-panel min-w-0" data-table-tools>
                <div class="border-b border-slate-200 px-5 py-4"><h3 class="font-bold text-slate-900">Riwayat Bonus</h3></div>
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari transaksi..." data-table-search></label>
                    <label>Tampilkan<select data-page-size><option value="5" @selected(request('per_page', '5') === '5')>5</option><option value="10" @selected(request('per_page') === '10')>10</option><option value="25" @selected(request('per_page') === '25')>25</option><option value="50" @selected(request('per_page') === '50')>50</option><option value="all" @selected(request('per_page') === 'all')>Semua</option></select></label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
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
