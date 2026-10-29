<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Nasabah</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-7xl" data-table-tools x-data="{ passwordOpen:false, deleteOpen:false, passwordMode:'reset', borrowerId:'', borrowerName:'', deleteBorrowerId:'', deleteBorrowerName:'' }" @borrower-password-open.window="passwordOpen=true; borrowerId=$event.detail.id; borrowerName=$event.detail.name; passwordMode='reset'" @borrower-delete-open.window="deleteOpen=true; deleteBorrowerId=$event.detail.id; deleteBorrowerName=$event.detail.name">
            @if (session('status'))<div class="dk-form-success mb-3">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="dk-form-alert mb-3">{{ $errors->first() }}</div>@endif
            <section class="dk-data-panel min-w-0">
                <div class="dk-table-toolbar">
                    @if (auth()->user()->isManager() || auth()->user()->isCashier())
                        <button type="button" class="dk-button-primary" data-open-dialog="#borrower-create-dialog">Registrasi</button>
                    @endif
                    <label>Filter <input type="search" placeholder="Cari nasabah..." data-table-search></label>
                    <label>Tampilkan<select data-page-size><option value="5" @selected($perPage === 5)>5</option><option value="10" @selected($perPage === 10)>10</option><option value="25" @selected($perPage === 25)>25</option><option value="50" @selected($perPage === 50)>50</option><option value="all" @selected($perPage === 'all')>Semua</option></select></label>
                    <div class="dk-view-toggle" aria-label="Mode tampilan"><button type="button" class="is-active" data-view-mode="table" aria-label="Tabel" title="Tabel">▤</button><button type="button" data-view-mode="cards" aria-label="Kartu" title="Kartu">▦</button></div>
                </div>
                <div class="overflow-x-auto dk-table-view" data-table-view="table">
                    <table class="w-full text-left text-sm">
                        <thead><tr><th>Nama</th><th>Username</th><th>HP</th><th>Status akun</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($borrowers as $borrower)
                                <tr data-table-row>
                                    <td class="font-bold">{{ $borrower->full_name }}</td>
                                    <td>{{ $borrower->user?->username }}</td>
                                    <td>{{ $borrower->phone ?: '—' }}</td>
                                    <td>{{ $borrower->user ? 'Aktif' : 'Akun tanpa login' }}</td>
                                    <td class="whitespace-nowrap">
                                        @if ($borrower->user)
                                            <button type="button" class="dk-inline-action" @click="$dispatch('borrower-password-open', { id: {{ $borrower->id }}, name: @js($borrower->full_name) })">Ganti Password</button>
                                        @endif
                                        @if (auth()->user()->isManager())
                                            @if ($borrower->loans_count === 0 && $borrower->advance_transactions_count === 0)
                                                <button type="button" class="dk-inline-action dk-inline-danger" @click="$dispatch('borrower-delete-open', { id: {{ $borrower->id }}, name: @js($borrower->full_name) })">Hapus</button>
                                            @else
                                                <span class="text-xs text-slate-500" title="Data dengan riwayat keuangan tidak dapat dihapus">Riwayat tersimpan</span>
                                            @endif
                                        @endif
                                    </td>
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
                            <div><strong>{{ $borrower->full_name }}</strong><span>{{ $borrower->user ? 'Akun aktif' : 'Akun tanpa login' }}</span></div>
                            <p>{{ $borrower->user?->username }} · {{ $borrower->phone ?: 'Nomor HP belum diisi' }}</p>
                            @if ($borrower->user)<button type="button" class="dk-inline-action w-fit" @click="$dispatch('borrower-password-open', { id: {{ $borrower->id }}, name: @js($borrower->full_name) })">Ganti Password</button>@endif
                            @if (auth()->user()->isManager())
                                @if ($borrower->loans_count === 0 && $borrower->advance_transactions_count === 0)
                                    <button type="button" class="dk-inline-action dk-inline-danger w-fit" @click="$dispatch('borrower-delete-open', { id: {{ $borrower->id }}, name: @js($borrower->full_name) })">Hapus akun</button>
                                @else
                                    <small>Riwayat keuangan tersimpan; akun ini tidak dapat dihapus.</small>
                                @endif
                            @endif
                        </article>
                    @endforeach
                </div>
                @if ($borrowers instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $borrowers->links() }}</div>@endif
            </section>

            @if (auth()->user()->isManager() || auth()->user()->isCashier())
                <dialog class="dk-password-native-dialog" id="borrower-create-dialog" @if ($errors->any() && old('_form') === 'borrower-create') data-open-on-error @endif>
                    <form method="POST" action="{{ route('cooperative.borrowers.store') }}" class="dk-password-form">
                        @csrf
                        <input type="hidden" name="_form" value="borrower-create">
                        <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
                        <p class="dk-eyebrow">AKUN NASABAH</p>
                        <h3>Registrasi nasabah</h3>
                        <label>Nama lengkap<input name="full_name" required maxlength="150" value="{{ old('full_name') }}"></label>
                        <label>Nomor HP<input name="phone" required maxlength="30" value="{{ old('phone') }}"></label>
                        <button class="dk-button-primary">Simpan nasabah</button>
                    </form>
                </dialog>
            @endif

            <div class="dk-modal-backdrop" x-show="passwordOpen" x-transition.opacity x-cloak @click.self="passwordOpen=false" @keydown.escape.window="passwordOpen=false">
                <section class="dk-password-dialog" role="dialog" aria-modal="true" aria-labelledby="borrower-password-title">
                    <button type="button" class="dk-modal-close" aria-label="Tutup" @click="passwordOpen=false">×</button>
                    <p class="dk-eyebrow">AKUN NASABAH</p>
                    <h3 id="borrower-password-title">Ganti Password <span x-text="borrowerName"></span></h3>
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
                            <label class="dk-confirmation-check"><input type="checkbox" required> Saya memahami password lama akan diganti.</label>
                            <button type="submit" class="dk-button-primary">Ganti Password</button>
                        </form>
                    </div>
                    @if (auth()->user()->isManager())
                        <form x-show="passwordMode === 'custom'" method="POST" :action="'{{ url('/cooperative/borrowers') }}/' + borrowerId + '/password'" class="dk-password-form">
                            @csrf
                            <label>Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                            <label>Ulangi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                            <label class="dk-confirmation-check"><input type="checkbox" required> Saya mengonfirmasi perubahan password.</label>
                            <button type="submit" class="dk-button-primary">Ganti Password</button>
                        </form>
                    @endif
                </section>
            </div>
            <div class="dk-modal-backdrop" x-show="deleteOpen" x-transition.opacity x-cloak @click.self="deleteOpen=false" @keydown.escape.window="deleteOpen=false">
                <section class="dk-password-dialog" role="dialog" aria-modal="true" aria-labelledby="borrower-delete-title">
                    <button type="button" class="dk-modal-close" aria-label="Tutup" @click="deleteOpen=false">×</button>
                    <p class="dk-eyebrow">KONFIRMASI PENGHAPUSAN</p>
                    <h3 id="borrower-delete-title">Hapus akun <span x-text="deleteBorrowerName"></span>?</h3>
                    <p class="mt-3 text-sm text-slate-600">Akun login dan data nasabah tanpa riwayat keuangan akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
                    <form method="POST" class="mt-5 flex justify-end gap-2" :action="'{{ url('/cooperative/borrowers') }}/' + deleteBorrowerId">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="dk-button-secondary" @click="deleteOpen=false">Batal</button>
                        <button type="submit" class="dk-button-danger">Hapus akun</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
