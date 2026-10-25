<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Nasabah</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-7xl grid items-stretch gap-6 lg:grid-cols-[minmax(300px,360px)_1fr]" data-table-tools>
            @if (auth()->user()->isManager())
                <form method="POST" action="{{ route('cooperative.borrowers.store') }}" class="dk-form-panel h-full">
                    @csrf
                    <h3 class="font-bold text-slate-900">Tambah Nasabah & Akun</h3>
                    <label class="mt-5 block text-xs font-bold uppercase text-slate-600">Nama Lengkap</label>
                    <input name="full_name" required value="{{ old('full_name') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <label class="mt-4 block text-xs font-bold uppercase text-slate-600">Nomor HP</label>
                    <input name="phone" required value="{{ old('phone') }}" class="mt-2 w-full rounded-md border-slate-300">
                    <button class="mt-5 w-full rounded-md bg-blue-600 px-4 py-3 font-bold text-white">Buat Nasabah</button>
                    @if ($errors->any())<div class="mt-4 text-sm text-red-600">{{ $errors->first() }}</div>@endif
                    @if (session('status'))<div class="mt-4 text-sm text-emerald-700">{{ session('status') }}</div>@endif
                </form>
            @else
                <div class="dk-form-panel h-full">
                    <h3 class="font-bold text-slate-900">Data Nasabah</h3>
                    @if (session('status'))<p class="mt-3 text-sm text-emerald-700">{{ session('status') }}</p>@endif
                </div>
            @endif
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="dk-table-toolbar"><label>Filter <input type="search" placeholder="Cari nasabah..." data-table-search></label><label>Tampilkan<select data-page-size><option value="10" @selected($perPage === 10)>10</option><option value="25" @selected($perPage === 25)>25</option><option value="50" @selected($perPage === 50)>50</option><option value="all" @selected($perPage === 'all')>Semua</option></select></label></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Kode</th><th>Nama</th><th>Username</th><th>HP</th><th>Pinjaman</th><th>Aksi</th></tr></thead><tbody>@foreach ($borrowers as $borrower)<tr data-table-row><td class="font-bold">{{ $borrower->borrower_code }}</td><td>{{ $borrower->full_name }}</td><td>{{ $borrower->user?->username }}</td><td>{{ $borrower->phone ?: '—' }}</td><td>{{ $borrower->loans->count() }} aktif/data</td><td>@if ($borrower->user)<form method="POST" action="{{ route('cooperative.borrowers.reset-password', $borrower) }}" onsubmit="return confirm('Reset password nasabah ini ke password awal?')">@csrf<button class="text-xs font-semibold text-blue-700 underline">Reset password</button></form>@endif</td></tr>@endforeach</tbody></table></div>
                @if ($borrowers instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="dk-table-footer">{{ $borrowers->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-app-layout>
