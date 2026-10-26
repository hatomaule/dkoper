<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Pinjaman</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-7xl grid items-stretch gap-5 lg:grid-cols-[minmax(240px,290px)_minmax(0,1fr)]" data-table-tools>
            <form method="POST" action="{{ route('cooperative.loans.store') }}" class="dk-form-panel self-start">
                @csrf
                <h3 class="font-bold text-slate-900">Ajukan Pinjaman</h3>
                @if (session('status'))<div class="mt-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="mt-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
                <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Nasabah</label>
                <select name="borrower_id" required class="mt-2 w-full rounded-md border-slate-300">
                    @foreach ($borrowers as $borrower)
                        <option value="{{ $borrower->id }}">{{ $borrower->full_name }}</option>
                    @endforeach
                </select>
                <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Pokok Pinjaman</label>
                <input name="principal_amount" type="text" inputmode="numeric" data-format-rupiah data-min="1000" required class="mt-2 w-full rounded-md border-slate-300" placeholder="1.000.000">
                <button class="dk-button-primary mt-5 w-fit">Catat</button>
            </form>
            <section class="dk-data-panel min-w-0">
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari nasabah / pinjaman..." data-table-search></label>
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
                        <button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button>
                        <button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button>
                    </div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th>Nasabah</th><th>Pokok</th><th>Tagihan</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($loans as $loan)
                                <tr data-table-row>
                                    <td class="font-bold">{{ $loan->borrower->full_name }}</td>
                                    <td>Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($loan->total_receivable, 0, ',', '.') }}</td>
                                    <td><span class="dk-status" data-status="{{ $loan->status }}">{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada pinjaman.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($loans as $loan)
                        <article class="dk-payment-card" data-table-row>
                            <div>
                                <strong>{{ $loan->borrower->full_name }}</strong>
                                <span class="dk-status" data-status="{{ $loan->status }}">{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</span>
                            </div>
                            <b>Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</b>
                            <small>Total tagihan Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}</small>
                        </article>
                    @endforeach
                </div>
                @if ($loans instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $loans->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
