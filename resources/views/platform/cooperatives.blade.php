@extends('platform.layout')

@section('page-title', 'Manajemen Koperasi')

@section('content')
    <dialog id="register-cooperative" @if ($errors->any()) data-open-on-error @endif class="w-[min(42rem,calc(100%-2rem))] rounded-2xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
        <form method="POST" action="{{ route('platform.cooperatives.store') }}" class="grid items-end gap-4 p-5 sm:grid-cols-2">
            @csrf
            <div class="sm:col-span-2">
                <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">Pendaftaran tenant</p>
                <h3 class="mt-1 text-lg font-bold text-white">Daftarkan koperasi</h3>
                <p class="mt-1 text-xs leading-5 text-slate-400">Setiap koperasi memiliki database, akun, dan transaksi tersendiri. Buat akun Owner awal untuk koperasi baru.</p>
            </div>
            @error('cooperative')<p class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-200 sm:col-span-2">{{ $message }}</p>@enderror
            <label class="block text-xs font-semibold text-slate-300">Nama koperasi
                <input name="name" value="{{ old('name') }}" required maxlength="150" class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-white outline-none transition focus:border-orange-400 focus:ring-1 focus:ring-orange-400">
                @error('name')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <label class="block text-xs font-semibold text-slate-300">Slug ruang
                <input name="slug" value="{{ old('slug') }}" required maxlength="50" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="koperasi-baru" class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-white outline-none transition focus:border-orange-400 focus:ring-1 focus:ring-orange-400">
                @error('slug')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <label class="block text-xs font-semibold text-slate-300">Username Owner
                <input name="owner_username" value="{{ old('owner_username') }}" required maxlength="50" class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-white outline-none transition focus:border-orange-400 focus:ring-1 focus:ring-orange-400">
                @error('owner_username')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-xs font-semibold text-slate-300">Password awal Owner
                    <input type="password" name="owner_password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-white outline-none focus:border-orange-400">
                    @error('owner_password')<span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs font-semibold text-slate-300">Ulangi password
                    <input type="password" name="owner_password_confirmation" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-white outline-none focus:border-orange-400">
                </label>
            </div>
            <p class="text-[11px] leading-5 text-slate-500 sm:col-span-2">Database dibuat terpisah; kredensial sementara Admin ditampilkan satu kali setelah pendaftaran berhasil.</p>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" data-close-dialog class="rounded-lg border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-300">Batal</button>
                <button type="submit" class="rounded-lg bg-orange-500 px-5 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-orange-400">Buat ruang koperasi</button>
            </div>
        </form>
    </dialog>

    <section class="overflow-hidden rounded-xl border border-slate-800 bg-[#101829] is-card-view" data-list-tools>
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-5 py-4">
            <div>
                <h3 class="font-bold text-white">Daftar koperasi</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $cooperatives->count() }} ruang terdaftar · pengguna dan nasabah dihitung per tenant</p>
            </div>
            <div class="flex items-center gap-1 rounded-lg border border-slate-700 p-1" aria-label="Mode tampilan">
                <button type="button" data-list-view-mode="table" aria-label="Tampilan tabel" title="Tampilan tabel" class="rounded-md p-2 text-slate-300 hover:bg-slate-700">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="1.6"><path d="M3 4.5h14M3 9.5h14M3 14.5h14M3 3h14v14H3z"/></svg>
                </button>
                <button type="button" data-list-view-mode="cards" aria-label="Tampilan kartu" title="Tampilan kartu" class="is-active rounded-md bg-slate-700 p-2 text-white">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="1.6"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/></svg>
                </button>
            </div>
        </header>
        <div class="flex flex-wrap items-end gap-3 border-b border-slate-800 px-5 py-4">
            <button type="button" data-open-dialog="register-cooperative" class="inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-bold text-slate-950 shadow-lg shadow-orange-950/20 transition hover:bg-orange-400">
                <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 fill-none stroke-current" stroke-width="2"><path d="M10 3v14M3 10h14"/></svg>
                Daftar Koperasi
            </button>
            <label class="min-w-[220px] flex-1 text-[11px] font-semibold text-slate-400">Cari koperasi
                <input type="search" data-list-search placeholder="Nama atau slug koperasi..." class="mt-1 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
            </label>
            <label class="text-[11px] font-semibold text-slate-400">Status
                <select data-list-filter="status" class="mt-1 block rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    <option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option><option value="provisioning">Menyiapkan</option><option value="provisioning_failed">Gagal</option>
                </select>
            </label>
            <label class="text-[11px] font-semibold text-slate-400">Baris
                <select data-list-page-size class="mt-1 block rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2 text-sm text-white outline-none focus:border-orange-400">
                    <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="all">Semua</option>
                </select>
            </label>
        </div>

        <div data-list-view="table" hidden class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-xs">
                <thead class="bg-[#0b1120] text-[10px] uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Koperasi</th><th class="px-4 py-3">Akun</th><th class="px-4 py-3">Nasabah</th><th class="px-4 py-3">Status</th><th class="px-5 py-3">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($cooperatives as $overview)
                        @php($cooperative = $overview['cooperative'])
                        <tr data-list-row data-list-key="cooperative-{{ $cooperative->id }}" data-status="{{ $cooperative->status }}" class="align-middle">
                            <td class="px-5 py-4">
                                <a href="{{ route('platform.cooperatives.show', $cooperative) }}" class="font-semibold text-white hover:text-orange-300">{{ $cooperative->name }}</a>
                                <p class="mt-1 text-[11px] text-slate-500">/{{ $cooperative->slug }} · Dibuat {{ $cooperative->created_at?->format('d M Y') }}</p>
                            </td>
                            <td class="px-4 py-4"><span class="font-semibold text-slate-200">{{ number_format($overview['stats']['users'], 0, ',', '.') }}</span><span class="ml-1 text-slate-500">akun</span></td>
                            <td class="px-4 py-4 font-semibold text-slate-200">{{ number_format($overview['stats']['borrowers'], 0, ',', '.') }}</td>
                            <td class="px-4 py-4">
                                <span @class(['rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide', 'bg-emerald-500/10 text-emerald-300' => $cooperative->status === 'active', 'bg-amber-500/10 text-amber-300' => $cooperative->status === 'provisioning', 'bg-rose-500/10 text-rose-300' => $cooperative->status === 'provisioning_failed', 'bg-slate-700 text-slate-300' => ! in_array($cooperative->status, ['active', 'provisioning', 'provisioning_failed'], true)])>{{ str_replace('_', ' ', $cooperative->status) }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('platform.cooperatives.show', $cooperative) }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:text-white">Detail</a>
                                    @if (in_array($cooperative->status, ['active', 'inactive'], true))
                                        <button type="button" data-open-dialog="cooperative-status-{{ $cooperative->id }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:text-white">{{ $cooperative->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    @endif
                                    @if (in_array($cooperative->status, ['active', 'inactive', 'deletion_failed'], true))
                                        <button type="button" data-open-dialog="cooperative-delete-{{ $cooperative->id }}" class="rounded-md border border-rose-500/40 px-2.5 py-2 text-[11px] font-semibold text-rose-300 hover:bg-rose-500/10">Hapus</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div data-list-view="cards" class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($cooperatives as $overview)
                @php($cooperative = $overview['cooperative'])
                <article data-list-row data-list-key="cooperative-{{ $cooperative->id }}" data-status="{{ $cooperative->status }}" class="platform-card-item p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('platform.cooperatives.show', $cooperative) }}" class="font-semibold text-white hover:text-orange-300">{{ $cooperative->name }}</a>
                            <p class="mt-1 break-all text-[11px] text-slate-500">/{{ $cooperative->slug }}</p>
                        </div>
                        <span class="rounded-full bg-slate-700 px-2 py-1 text-[10px] font-bold uppercase text-slate-300">{{ str_replace('_', ' ', $cooperative->status) }}</span>
                    </div>
                    <div class="mt-4 flex gap-5 text-xs"><span><strong class="text-white">{{ number_format($overview['stats']['users'], 0, ',', '.') }}</strong> akun</span><span><strong class="text-white">{{ number_format($overview['stats']['borrowers'], 0, ',', '.') }}</strong> nasabah</span></div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('platform.cooperatives.show', $cooperative) }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:text-white">Detail koperasi</a>
                        @if (in_array($cooperative->status, ['active', 'inactive'], true))
                            <button type="button" data-open-dialog="cooperative-status-{{ $cooperative->id }}" class="rounded-md border border-slate-700 px-2.5 py-2 text-[11px] font-semibold text-slate-300 hover:text-white">{{ $cooperative->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        @endif
                        @if (in_array($cooperative->status, ['active', 'inactive', 'deletion_failed'], true))
                            <button type="button" data-open-dialog="cooperative-delete-{{ $cooperative->id }}" class="rounded-md border border-rose-500/40 px-2.5 py-2 text-[11px] font-semibold text-rose-300 hover:bg-rose-500/10">Hapus</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <p data-list-empty hidden class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada koperasi yang cocok.</p>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-5 py-3 text-[11px] text-slate-500">
            <span data-list-summary></span>
            <div class="flex items-center gap-2"><button type="button" data-list-prev class="rounded-md border border-slate-700 px-3 py-1.5 hover:text-white">Sebelumnya</button><span data-list-page></span><button type="button" data-list-next class="rounded-md border border-slate-700 px-3 py-1.5 hover:text-white">Berikutnya</button></div>
        </footer>
    </section>

    @foreach ($cooperatives as $overview)
        @php($cooperative = $overview['cooperative'])
        @if (in_array($cooperative->status, ['active', 'inactive'], true))
            <dialog id="cooperative-status-{{ $cooperative->id }}" class="w-[min(28rem,calc(100%-2rem))] rounded-xl border border-slate-700 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
                <form method="POST" action="{{ route('platform.cooperatives.status', $cooperative) }}" class="space-y-4 p-5">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $cooperative->status === 'active' ? 'inactive' : 'active' }}">
                    <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-400">Status koperasi</p>
                    <h3 class="text-lg font-bold text-white">{{ $cooperative->status === 'active' ? 'Nonaktifkan koperasi?' : 'Aktifkan koperasi?' }}</h3>
                    <p class="text-sm text-slate-400">{{ $cooperative->status === 'active' ? 'Semua akun pada ruang ini tidak dapat masuk sampai koperasi diaktifkan kembali. Data tidak dihapus.' : 'Akun koperasi ini akan dapat digunakan kembali.' }}</p>
                    <label class="flex gap-2 text-xs text-slate-400"><input type="checkbox" name="confirm_action" value="yes" required> Saya memahami perubahan akses pada koperasi ini.</label>
                    <div class="flex justify-end gap-2"><button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs">Batal</button><button type="submit" class="rounded-md {{ $cooperative->status === 'active' ? 'bg-rose-500 text-white' : 'bg-orange-500 text-slate-950' }} px-3 py-2 text-xs font-bold">{{ $cooperative->status === 'active' ? 'Ya, nonaktifkan' : 'Ya, aktifkan' }}</button></div>
                </form>
            </dialog>
        @endif
        @if (in_array($cooperative->status, ['active', 'inactive', 'deletion_failed'], true))
            <dialog id="cooperative-delete-{{ $cooperative->id }}" @if ((int) old('cooperative_id', 0) === $cooperative->id && $errors->any()) data-open-on-error @endif class="w-[min(32rem,calc(100%-2rem))] rounded-xl border border-rose-500/40 bg-[#101829] p-0 text-slate-200 shadow-2xl backdrop:bg-black/70">
                <form method="POST" action="{{ route('platform.cooperatives.destroy', $cooperative) }}" class="space-y-4 p-5">
                    @csrf @method('DELETE')
                    <input type="hidden" name="cooperative_id" value="{{ $cooperative->id }}">
                    <button type="button" data-close-dialog class="float-right rounded-md px-2 text-xl text-slate-500 hover:text-white" aria-label="Tutup">×</button>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-rose-300">Penghapusan permanen</p>
                    <h3 class="text-lg font-bold text-white">Hapus koperasi {{ $cooperative->name }}?</h3>
                    <p class="text-sm leading-6 text-slate-400">Tindakan ini menghapus database tenant beserta seluruh akun, nasabah, pinjaman, setoran, dan transaksi. Tindakan tidak dapat dibatalkan. Pastikan cadangan data tersedia sebelum melanjutkan.</p>
                    @if ((int) old('cooperative_id', 0) === $cooperative->id)
                        @error('cooperative_name_confirmation')<p class="rounded-md border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-200">{{ $message }}</p>@enderror
                        @error('cooperative_delete.'.$cooperative->id)<p class="rounded-md border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-200">{{ $message }}</p>@enderror
                    @endif
                    <label class="block text-xs font-semibold text-slate-300">Ketik <span class="text-white">{{ $cooperative->name }}</span> untuk konfirmasi
                        <input name="cooperative_name_confirmation" value="{{ old('cooperative_name_confirmation') }}" required autocomplete="off" class="mt-1.5 block w-full rounded-md border border-slate-700 bg-[#0b1120] px-3 py-2.5 text-sm text-white outline-none focus:border-rose-400">
                    </label>
                    <label class="flex items-start gap-2 text-xs leading-5 text-slate-400"><input type="checkbox" name="confirm_permanent_deletion" value="yes" required class="mt-1"> Saya memahami seluruh database dan riwayat finansial koperasi ini akan dihapus permanen.</label>
                    <div class="flex justify-end gap-2">
                        <button type="button" data-close-dialog class="rounded-md border border-slate-700 px-3 py-2 text-xs font-semibold">Batal</button>
                        <button type="submit" class="rounded-md bg-rose-500 px-3 py-2 text-xs font-bold text-white hover:bg-rose-400">Hapus permanen</button>
                    </div>
                </form>
            </dialog>
        @endif
    @endforeach
@endsection
