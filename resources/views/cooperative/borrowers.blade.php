<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Nasabah</h2></x-slot>
    <div class="bg-slate-100 min-h-[calc(100vh-65px)] py-8 px-4">
        <div class="mx-auto max-w-6xl grid gap-6 lg:grid-cols-[360px_1fr]">
            <form method="POST" action="{{ route('cooperative.borrowers.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm h-fit">
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
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Kode</th><th class="px-5 py-3">Nama</th><th class="px-5 py-3">Username</th><th class="px-5 py-3">HP</th><th class="px-5 py-3">Pinjaman</th></tr></thead><tbody>@foreach ($borrowers as $borrower)<tr class="border-t border-slate-100"><td class="px-5 py-3 font-bold">{{ $borrower->borrower_code }}</td><td class="px-5 py-3">{{ $borrower->full_name }}</td><td class="px-5 py-3">{{ $borrower->user?->username }}</td><td class="px-5 py-3">{{ $borrower->phone }}</td><td class="px-5 py-3">{{ $borrower->loans->count() }} aktif/data</td></tr>@endforeach</tbody></table></div>
            </div>
        </div>
    </div>
</x-app-layout>
