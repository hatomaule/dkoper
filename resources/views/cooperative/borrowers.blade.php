<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Nasabah</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-7xl grid items-stretch gap-6 lg:grid-cols-[minmax(300px,360px)_1fr]" data-table-tools x-data="{ passwordOpen:false, passwordMode:'reset', borrowerId:'', borrowerName:'' }" @borrower-password-open.window="passwordOpen=true; borrowerId=$event.detail.id; borrowerName=$event.detail.name; passwordMode='reset'">
            @if (auth()->user()->isManager())
                <form method="POST" action="{{ route('cooperative.borrowers.store') }}" class="dk-form-panel h-full">
                    @csrf
                    <h3 class="font-bold text-slate-900">Tambah Nasabah & Akun</h3>
                    <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Nama Lengkap</label>
                    <input name="full_name" required value="{{ old('full_name') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Nomor HP</label>
                    <input name="phone" required value="{{ old('phone') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <button class="dk-button-primary mt-5 w-full">Catat</button>
                    @if ($errors->any())<div class="mt-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
                    @if (session('status'))<div class="mt-4 text-sm text-emerald-700">{{ session('status') }}</div>@endif
                </form>
            @else
                <div class="dk-form-panel h-full">
                    <h3 class="font-bold text-slate-900">Data Nasabah</h3>
                    @if (session('status'))<p class="mt-3 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                </div>
            @endif
            <section class="dk-data-panel min-w-0">
                <div class="dk-table-toolbar">
                    <label>Filter <input type="search" placeholder="Cari nasabah..." data-table-search></label>
                    <label>Tampilkan<select data-page-size><option value="5" @selected($perPage === 5)>5</option><option value="10" @selected($perPage === 10)>10</option><option value="25" @selected($perPage === 25)>25</option><option value="50" @selected($perPage === 50)>50</option><option value="all" @selected($perPage === 'all')>Semua</option></select></label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th>Nama</th><th>Username</th><th>HP</th><th>Pinjaman</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($borrowers as $borrower)
                                <tr data-table-row>
                                    <td class="font-bold">{{ $borrower->full_name }}</td><td>{{ $borrower->user?->username }}</td><td>{{ $borrower->phone ?: '—' }}</td><td>{{ $borrower->loans->count() }}</td>
                                    <td>@if ($borrower->user)<button type="button" class="dk-inline-action" @click="$dispatch('borrower-password-open', { id: {{ $borrower->id }}, name: @js($borrower->full_name) })">Password</button>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada nasabah.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dk-payment-cards dk-table-view" data-table-view="cards" hidden>
                    @foreach ($borrowers as $borrower)
                        <article class="dk-payment-card" data-table-row>
                            <div><strong>{{ $borrower->full_name }}</strong><span>{{ $borrower->loans->count() }} pinjaman</span></div>
                            <p>{{ $borrower->user?->username }} · {{ $borrower->phone ?: 'Nomor HP belum diisi' }}</p>
                            @if ($borrower->user)<button type="button" class="dk-inline-action w-fit" @click="$dispatch('borrower-password-open', { id: {{ $borrower->id }}, name: @js($borrower->full_name) })">Kelola password</button>@endif
                        </article>
                    @endforeach
                </div>
                @if ($borrowers instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $borrowers->links() }}</div>@endif
            </section>

            <div class="dk-modal-backdrop" x-show="passwordOpen" x-transition.opacity x-cloak @click.self="passwordOpen=false" @keydown.escape.window="passwordOpen=false">
                <section class="dk-password-dialog" role="dialog" aria-modal="true" aria-labelledby="borrower-password-title">
                    <button type="button" class="dk-modal-close" aria-label="Tutup" @click="passwordOpen=false">×</button>
                    <p class="dk-eyebrow">AKUN NASABAH</p>
                    <h3 id="borrower-password-title">Password <span x-text="borrowerName"></span></h3>
                    <div class="dk-password-mode" role="group" aria-label="Pilihan password">
                        <button type="button" :class="{ 'is-active': passwordMode === 'reset' }" @click="passwordMode='reset'">Reset 123456</button>
                        @if (auth()->user()->isManager())
                            <button type="button" :class="{ 'is-active': passwordMode === 'custom' }" @click="passwordMode='custom'">Ganti password</button>
                        @endif
                    </div>
                    <div x-show="passwordMode === 'reset'" class="dk-password-reset">
                        <p>Password akan diatur menjadi <strong>123456</strong> dan harus diganti saat nasabah masuk.</p>
                        <form method="POST" :action="'{{ url('/cooperative/borrowers') }}/' + borrowerId + '/reset-password'">
                            @csrf
                            <button type="submit" class="dk-button-primary">Reset password</button>
                        </form>
                    </div>
                    @if (auth()->user()->isManager())
                        <form x-show="passwordMode === 'custom'" method="POST" :action="'{{ url('/cooperative/borrowers') }}/' + borrowerId + '/password'" class="dk-password-form">
                            @csrf
                            <label>Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                            <label>Ulangi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                            <button type="submit" class="dk-button-primary">Simpan password</button>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
