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

    @php($maxCooperativeUsers = max(1, (int) $cooperatives->max(fn ($overview) => max($overview['stats']['users'], $overview['stats']['borrowers']))))
    <section class="rounded-xl border border-slate-800 bg-[#101829] p-5 shadow-lg shadow-black/10">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-bold text-white">Ringkasan koperasi</h2>
                <p class="mt-1 text-xs text-slate-500">Perbandingan jumlah akun dan nasabah pada setiap koperasi.</p>
            </div>
            <div class="flex gap-4 text-[11px] text-slate-400">
                <span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-sm bg-orange-400"></i>Akun</span>
                <span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-sm bg-sky-400"></i>Nasabah</span>
            </div>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($cooperatives->take(9) as $overview)
                @php($cooperative = $overview['cooperative'])
                <article class="min-w-0 rounded-lg border border-slate-800 bg-[#0b1120] p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <a href="{{ route('platform.cooperatives.show', $cooperative) }}" class="truncate text-xs font-semibold text-slate-200 hover:text-orange-300">{{ $cooperative->name }}</a>
                        <span class="shrink-0 text-[10px] text-slate-500">{{ number_format($overview['stats']['users'], 0, ',', '.') }} / {{ number_format($overview['stats']['borrowers'], 0, ',', '.') }}</span>
                    </div>
                    <div class="grid gap-2" role="img" aria-label="{{ $cooperative->name }}: {{ $overview['stats']['users'] }} akun dan {{ $overview['stats']['borrowers'] }} nasabah">
                        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full rounded-full bg-orange-400 transition-all" style="width: {{ min(100, (int) round($overview['stats']['users'] / $maxCooperativeUsers * 100)) }}%"></div>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full rounded-full bg-sky-400 transition-all" style="width: {{ min(100, (int) round($overview['stats']['borrowers'] / $maxCooperativeUsers * 100)) }}%"></div>
                        </div>
                    </div>
                </article>
            @empty
                <p class="py-6 text-sm text-slate-500">Belum ada data koperasi untuk ditampilkan.</p>
            @endforelse
        </div>
        @if ($cooperatives->count() > 9)
            <p class="mt-3 text-right text-[11px] text-slate-500">Grafik menampilkan 9 koperasi terbaru.</p>
        @endif
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Koperasi terdaftar', number_format($totals['cooperatives'], 0, ',', '.'), '◈', 'text-orange-300'],
            ['Koperasi aktif', number_format($totals['active'], 0, ',', '.'), '✓', 'text-emerald-300'],
            ['Akun pengguna', number_format($totals['users'], 0, ',', '.'), '♙', 'text-sky-300'],
            ['Nasabah terdaftar', number_format($totals['borrowers'], 0, ',', '.'), '◎', 'text-violet-300'],
        ] as [$label, $value, $icon, $color])
            <article class="rounded-xl border border-slate-800 bg-[#101829] p-5 shadow-lg shadow-black/10">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <span class="{{ $color }} text-lg">{{ $icon }}</span>
                </div>
                <p class="mt-4 text-3xl font-bold tracking-tight text-white">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-500">Akumulasi dari ruang koperasi</p>
            </article>
        @endforeach
    </section>

@endsection
