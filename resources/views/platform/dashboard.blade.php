@extends('platform.layout')

@section('page-title', 'Dashboard')

@section('content')
    @if (session('temporary_admin_credentials'))
        <section class="rounded-xl border border-amber-400/40 bg-amber-400/10 p-4 text-sm text-amber-100">
            <h2 class="font-bold">Simpan akses Admin ini sekarang</h2>
            <p class="mt-1 text-amber-100/70">Password hanya ditampilkan sekali. Minta Admin menggantinya saat pertama masuk.</p>
            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 font-mono">
                <span>Username: {{ session('temporary_admin_credentials.username') }}</span>
                <span>Password: {{ session('temporary_admin_credentials.password') }}</span>
            </div>
        </section>
    @endif

    <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ([
            ['Koperasi terdaftar', number_format($totals['cooperatives'], 0, ',', '.'), '◈', 'text-orange-300'],
            ['Koperasi aktif', number_format($totals['active'], 0, ',', '.'), '✓', 'text-emerald-300'],
            ['Akun pengguna', number_format($totals['users'], 0, ',', '.'), '♙', 'text-sky-300'],
            ['Nasabah terdaftar', number_format($totals['borrowers'], 0, ',', '.'), '◎', 'text-violet-300'],
        ] as [$label, $value, $icon, $color])
            <article class="min-w-0 rounded-xl border border-slate-800 bg-[#101829] p-3.5 shadow-lg shadow-black/10 transition duration-200 hover:-translate-y-0.5 hover:border-slate-700 sm:p-5">
                <div class="flex min-h-9 items-start justify-between gap-2 sm:items-center">
                    <p class="text-[10px] font-semibold uppercase leading-4 tracking-wide text-slate-400 sm:text-xs">{{ $label }}</p>
                    <span class="{{ $color }} shrink-0 text-base sm:text-lg">{{ $icon }}</span>
                </div>
                <p class="mt-2 text-2xl font-bold tracking-tight text-white sm:mt-4 sm:text-3xl">{{ $value }}</p>
                <p class="mt-1 text-[10px] leading-4 text-slate-500 sm:text-xs">Akumulasi dari ruang koperasi</p>
            </article>
        @endforeach
    </section>

    <section aria-label="Informasi koperasi">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-white sm:text-base">Informasi koperasi</h2>
            <span class="text-[11px] text-slate-500">{{ number_format($cooperatives->count(), 0, ',', '.') }} koperasi</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($cooperatives as $overview)
                @php($cooperative = $overview['cooperative'])
                <article class="min-w-0 rounded-xl border border-slate-800 bg-[#101829] p-4 transition duration-200 hover:-translate-y-0.5 hover:border-slate-700 hover:shadow-lg hover:shadow-black/10 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('platform.cooperatives.show', $cooperative) }}" class="min-w-0 break-words text-sm font-semibold text-white transition hover:text-orange-300 sm:text-base">{{ $cooperative->name }}</a>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase tracking-wide sm:text-[10px]',
                            'bg-emerald-500/10 text-emerald-300' => $cooperative->status === 'active',
                            'bg-amber-500/10 text-amber-300' => $cooperative->status === 'provisioning',
                            'bg-rose-500/10 text-rose-300' => $cooperative->status === 'provisioning_failed',
                            'bg-slate-700 text-slate-300' => ! in_array($cooperative->status, ['active', 'provisioning', 'provisioning_failed'], true),
                        ])>{{ str_replace('_', ' ', $cooperative->status) }}</span>
                    </div>
                    <p class="mt-1 truncate text-[11px] text-slate-500">/{{ $cooperative->slug }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:gap-3">
                        <div class="rounded-lg border border-slate-800/80 bg-[#0b1120]/60 px-3 py-2.5">
                            <p class="text-[10px] font-medium text-slate-500 sm:text-[11px]">Akun pengguna</p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-sky-300">{{ number_format($overview['stats']['users'], 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-800/80 bg-[#0b1120]/60 px-3 py-2.5">
                            <p class="text-[10px] font-medium text-slate-500 sm:text-[11px]">Nasabah</p>
                            <p class="mt-1 text-lg font-bold tabular-nums text-cyan-300">{{ number_format($overview['stats']['borrowers'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-slate-800 px-4 py-8 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Belum ada koperasi terdaftar.</p>
            @endforelse
        </div>
    </section>

@endsection
