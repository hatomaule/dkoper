<section id="cooperative-{{ $cooperative->id }}" class="scroll-mt-24 overflow-hidden rounded-xl border border-slate-800 bg-[#101829]" data-list-tools>
    <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 px-5 py-4">
        <div>
            <p class="font-bold text-white">{{ $cooperative->name }}</p>
            <p class="mt-1 text-xs text-slate-500">/{{ $cooperative->slug }} · {{ number_format($stats['staff'], 0, ',', '.') }} akun Owner/Admin</p>
        </div>
        <span @class([
            'rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
            'bg-emerald-500/10 text-emerald-300' => $cooperative->status === 'active',
            'bg-amber-500/10 text-amber-300' => $cooperative->status === 'provisioning',
            'bg-rose-500/10 text-rose-300' => $cooperative->status === 'provisioning_failed',
            'bg-slate-700 text-slate-300' => ! in_array($cooperative->status, ['active', 'provisioning', 'provisioning_failed'], true),
        ])>{{ str_replace('_', ' ', $cooperative->status) }}</span>
    </header>

    @if ($cooperative->status !== 'active')
        <p class="px-5 py-5 text-sm text-slate-500">Pengelolaan akun tersedia setelah ruang koperasi aktif.</p>
    @else
        <div class="flex flex-wrap items-end gap-3 border-b border-slate-800 px-5 py-4">
            <button type="button" data-open-dialog="create-staff-account-{{ $cooperative->id }}" class="rounded-md bg-orange-500 px-4 py-2 text-xs font-bold text-slate-950 transition hover:bg-orange-400">Create</button>
            <label class="min-w-[220px] flex-1 text-[11px] font-semibold text-slate-400">Cari akun
                <input type="search" data-list-search placeholder="Nama, username, koperasi..." class="mt-1 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
            </label>
            <label class="text-[11px] font-semibold text-slate-400">Status
                <select data-list-filter="status" class="mt-1 block rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    <option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option>
                </select>
            </label>
            <label class="text-[11px] font-semibold text-slate-400">Baris
                <select data-list-page-size class="mt-1 block rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="all">Semua</option>
                </select>
            </label>
            <div class="flex items-center gap-1 rounded-lg border border-slate-700 p-1" aria-label="Mode tampilan">
                <button type="button" data-list-view-mode="table" aria-label="Tampilan tabel" title="Tampilan tabel" class="rounded-md p-2 text-slate-300 hover:bg-slate-700">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="1.6"><path d="M3 4.5h14M3 9.5h14M3 14.5h14M3 3h14v14H3z"/></svg>
                </button>
                <button type="button" data-list-view-mode="cards" aria-label="Tampilan kartu" title="Tampilan kartu" class="is-active rounded-md bg-slate-700 p-2 text-white">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="1.6"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/></svg>
                </button>
            </div>
        </div>
        <div data-list-view="table" hidden class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-xs">
                <thead class="bg-[#0b1120] text-[10px] uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Akun</th><th class="px-4 py-3">Username / kode</th><th class="px-4 py-3">Peran</th><th class="px-4 py-3">Status / aktivitas</th><th class="px-5 py-3">Kelola akun</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($accounts as $account)
                        @php($roleLabel = $account->role === 'manager' ? 'Owner' : 'Admin')
                        <tr data-list-row data-list-key="account-{{ $account->id }}" data-role="{{ $account->role }}" data-status="{{ $account->is_active ? 'active' : 'inactive' }}" class="align-top">
                            <td class="px-5 py-4">
                                <form id="account-table-form-{{ $account->id }}" method="POST" action="{{ route('platform.accounts.update', [$cooperative->id, $account->id]) }}" class="grid min-w-[210px] gap-2">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="account-table-name-{{ $account->id }}">Nama akun</label>
                                    <input id="account-table-name-{{ $account->id }}" name="name" value="{{ $account->name }}" required maxlength="255" class="w-full rounded-md border border-slate-700 bg-[#0b1120] px-2.5 py-2 text-sm font-semibold text-white outline-none focus:border-orange-400">
                                </form>
                            </td>
                            <td class="px-4 py-4">
                                <label class="sr-only" for="account-table-username-{{ $account->id }}">Username</label>
                                <input id="account-table-username-{{ $account->id }}" form="account-table-form-{{ $account->id }}" name="username" value="{{ $account->username }}" maxlength="50" pattern="[A-Za-z0-9_-]+" placeholder="Username" class="w-full rounded-md border border-slate-700 bg-[#0b1120] px-2.5 py-2 text-xs text-slate-200 outline-none focus:border-orange-400">
                                <span class="mt-2 block text-[11px] text-slate-500">{{ $account->borrower_code ?: ($account->phone ?: '—') }}</span>
                            </td>
                            <td class="px-4 py-4"><span class="rounded-full bg-slate-700/70 px-2.5 py-1 text-[10px] font-bold text-slate-300">{{ $roleLabel }}</span></td>
                            <td class="px-4 py-4">
                                <span @class(['rounded-full px-2.5 py-1 text-[10px] font-bold', 'bg-emerald-500/10 text-emerald-300' => $account->is_active, 'bg-slate-700 text-slate-300' => ! $account->is_active])>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                <p class="mt-2 text-[10px] text-slate-500">
                                    @if ($account->must_change_password)
                                        Wajib ganti password
                                    @elseif ($account->last_login_at)
                                        Masuk {{ \Illuminate\Support\Carbon::parse($account->last_login_at)->format('d M Y, H:i') }}
                                    @else
                                        Belum pernah masuk
                                    @endif
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" form="account-table-form-{{ $account->id }}" class="rounded-md bg-orange-500/10 px-2.5 py-2 text-[11px] font-bold text-orange-300 hover:bg-orange-500/20">Simpan</button>
                                    <button type="button" data-open-dialog="reset-account-{{ $cooperative->id }}-{{ $account->id }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:border-orange-400/60 hover:text-white">Reset sandi</button>
                                    <button type="button" data-open-dialog="status-account-{{ $cooperative->id }}-{{ $account->id }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:border-slate-500 hover:text-white">{{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    <button type="button" data-open-dialog="delete-account-{{ $cooperative->id }}-{{ $account->id }}" class="rounded-md border border-rose-500/40 px-2.5 py-2 text-[11px] font-semibold text-rose-300 hover:bg-rose-500/10">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div data-list-view="cards" class="grid justify-start gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            @foreach ($accounts as $account)
                @php($roleLabel = $account->role === 'manager' ? 'Owner' : 'Admin')
                <article data-list-row data-list-key="account-{{ $account->id }}" data-role="{{ $account->role }}" data-status="{{ $account->is_active ? 'active' : 'inactive' }}" class="platform-card-item min-w-0 max-w-[320px] p-4">
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="rounded-full bg-slate-700/70 px-2.5 py-1 text-[10px] font-bold text-slate-300">{{ $roleLabel }}</span>
                            <span @class(['ml-1 rounded-full px-2.5 py-1 text-[10px] font-bold', 'bg-emerald-500/10 text-emerald-300' => $account->is_active, 'bg-slate-700 text-slate-300' => ! $account->is_active])>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <span class="shrink-0 text-[10px] text-slate-500">
                            @if ($account->must_change_password)
                                Wajib ganti sandi
                            @elseif ($account->last_login_at)
                                Masuk {{ \Illuminate\Support\Carbon::parse($account->last_login_at)->format('d M Y') }}
                            @else
                                Belum pernah masuk
                            @endif
                        </span>
                    </header>
                    <div class="mt-4 grid gap-3">
                        <div>
                            <span class="text-[10px] font-semibold text-slate-500">Nama</span>
                            <p class="mt-1 truncate text-sm font-semibold text-white">{{ $account->name }}</p>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold text-slate-500">Username</span>
                            <p class="mt-1 truncate text-sm text-slate-200">{{ $account->username ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-[10px] font-semibold text-slate-500">Peran</span>
                        <p class="mt-1 text-sm text-slate-200">{{ $roleLabel }}</p>
                    </div>
                    <p class="mt-3 truncate text-[11px] text-slate-500">{{ $account->borrower_code ?: ($account->phone ?: '—') }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-800 pt-3">
                        <button type="button" data-open-dialog="reset-account-{{ $cooperative->id }}-{{ $account->id }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:text-white">Reset sandi</button>
                        <button type="button" data-open-dialog="status-account-{{ $cooperative->id }}-{{ $account->id }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:text-white">{{ $account->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        <button type="button" data-open-dialog="delete-account-{{ $cooperative->id }}-{{ $account->id }}" class="rounded-md border border-rose-500/40 px-2.5 py-2 text-[11px] font-semibold text-rose-300 hover:bg-rose-500/10">Hapus</button>
                    </div>
                </article>
            @endforeach
        </div>
        <p data-list-empty hidden class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada akun yang cocok.</p>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-5 py-3 text-[11px] text-slate-500">
            <span data-list-summary></span>
            <div class="flex items-center gap-2">
                <button type="button" data-list-prev class="rounded-md border border-slate-700 px-3 py-1.5 hover:text-white">Sebelumnya</button>
                <span data-list-page></span>
                <button type="button" data-list-next class="rounded-md border border-slate-700 px-3 py-1.5 hover:text-white">Berikutnya</button>
            </div>
        </footer>
        <dialog id="create-staff-account-{{ $cooperative->id }}" @if ($errors->any() && old('_form') === 'create-staff-account-'.$cooperative->id) data-open-on-error @endif class="w-[min(30rem,calc(100%-2rem))] rounded-xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
            <form method="POST" action="{{ route('platform.accounts.store', $cooperative->id) }}" class="grid gap-4 p-5">
                @csrf
                <input type="hidden" name="_form" value="create-staff-account-{{ $cooperative->id }}">
                <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">Akun koperasi</p>
                    <h3 class="mt-1 text-lg font-bold text-white">Buat akun Owner/Admin</h3>
                    <p class="mt-1 text-xs text-slate-400">Akun nasabah dikelola dari sistem koperasi, bukan dari FIDES Platform.</p>
                </div>
                <label class="block text-xs font-semibold text-slate-300">Nama
                    <input name="name" value="{{ old('_form') === 'create-staff-account-'.$cooperative->id ? old('name') : '' }}" required maxlength="255" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    @error('name')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs font-semibold text-slate-300">Username
                    <input name="username" value="{{ old('_form') === 'create-staff-account-'.$cooperative->id ? old('username') : '' }}" required maxlength="50" pattern="[A-Za-z0-9_-]+" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    @error('username')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs font-semibold text-slate-300">Jenis akun
                    <select name="role" required class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                        <option value="manager" @selected(old('role', 'manager') === 'manager')>Owner</option>
                        <option value="kasir" @selected(old('role') === 'kasir')>Admin</option>
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-300">Password awal
                    <input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    @error('password')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs font-semibold text-slate-300">Ulangi password
                    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                </label>
                <p class="text-[11px] text-slate-500">Pengguna wajib mengganti password saat pertama kali masuk.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300">Batal</button>
                    <button type="submit" class="rounded-md bg-orange-500 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-orange-400">Buat akun</button>
                </div>
            </form>
        </dialog>
        @foreach ($accounts as $account)
            @php($dialogId = $cooperative->id.'-'.$account->id)
            <dialog id="reset-account-{{ $dialogId }}" class="w-[min(28rem,calc(100%-2rem))] rounded-xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
                <form method="POST" action="{{ route('platform.accounts.reset-password', [$cooperative->id, $account->id]) }}" class="space-y-4 p-5">
                    @csrf
                    <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">Keamanan akun</p><h3 class="text-lg font-bold text-white">Reset password</h3>
                    <p class="text-sm text-slate-400">Buat password baru untuk <strong class="text-white">{{ $account->name }}</strong>. Pengguna harus mengganti password saat masuk.</p>
                    <label class="block text-xs font-semibold text-slate-400">Password baru<input type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400"></label>
                    <label class="block text-xs font-semibold text-slate-400">Ulangi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400"></label>
                    <label class="flex gap-2 text-xs text-slate-400"><input type="checkbox" name="confirm_account_action" value="yes" required> Saya memahami password akun ini akan diganti.</label>
                    <div class="flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs">Batal</button><button type="submit" class="rounded-md bg-orange-500 px-3 py-2 text-xs font-bold text-slate-950">Simpan password</button></div>
                </form>
            </dialog>
            <dialog id="status-account-{{ $dialogId }}" class="w-[min(28rem,calc(100%-2rem))] rounded-xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
                <form method="POST" action="{{ route('platform.accounts.status', [$cooperative->id, $account->id]) }}" class="space-y-4 p-5">
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $account->is_active ? 0 : 1 }}">
                    <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">Konfirmasi akun</p><h3 class="text-lg font-bold text-white">{{ $account->is_active ? 'Nonaktifkan akun?' : 'Aktifkan akun?' }}</h3>
                    <p class="text-sm text-slate-400">{{ $account->is_active ? 'Akun ini tidak akan dapat masuk selama dinonaktifkan.' : 'Akun ini dapat masuk kembali setelah diaktifkan.' }}</p>
                    <label class="flex gap-2 text-xs text-slate-400"><input type="checkbox" name="confirm_account_action" value="yes" required> Saya memahami akun ini akan {{ $account->is_active ? 'dinonaktifkan' : 'diaktifkan' }}.</label>
                    <div class="flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs">Batal</button><button type="submit" class="rounded-md {{ $account->is_active ? 'bg-rose-500 text-white' : 'bg-orange-500 text-slate-950' }} px-3 py-2 text-xs font-bold">{{ $account->is_active ? 'Ya, nonaktifkan' : 'Ya, aktifkan' }}</button></div>
                </form>
            </dialog>
            <dialog id="delete-account-{{ $dialogId }}" class="w-[min(28rem,calc(100%-2rem))] rounded-xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
                <form method="POST" action="{{ route('platform.accounts.destroy', [$cooperative->id, $account->id]) }}" class="space-y-4 p-5">
                    @csrf @method('DELETE')
                    <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-rose-300">Konfirmasi hapus akun</p><h3 class="text-lg font-bold text-white">Hapus {{ $roleLabel }}?</h3>
                    <p class="text-sm text-slate-400">Akun <strong class="text-white">{{ $account->name }}</strong> hanya dapat dihapus jika tidak memiliki riwayat operasional atau keuangan. Jika memiliki riwayat, nonaktifkan akun agar catatan tetap utuh.</p>
                    <label class="flex gap-2 text-xs text-slate-400"><input type="checkbox" name="confirm_account_action" value="yes" required> Saya memahami akun tanpa riwayat ini akan dihapus permanen.</label>
                    <div class="flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs">Batal</button><button type="submit" class="rounded-md bg-rose-500 px-3 py-2 text-xs font-bold text-white">Hapus akun</button></div>
                </form>
            </dialog>
        @endforeach
    @endif
</section>
