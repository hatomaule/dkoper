@extends('platform.layout')

@section('page-title', $cooperative->name)

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('platform.cooperatives') }}" class="text-xs font-semibold text-orange-300 hover:text-orange-200">← Kembali ke koperasi</a>
            <h2 class="mt-2 text-2xl font-bold text-white">{{ $cooperative->name }}</h2>
            <p class="mt-1 text-sm text-slate-400">Pemantauan akun dan pengguna · /{{ $cooperative->slug }}</p>
        </div>
        <a href="{{ route('platform.accounts') }}#cooperative-{{ $cooperative->id }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-orange-400/60 hover:text-white">Semua akun</a>
    </div>

    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Total pengguna', $overview['stats']['users'], 'text-sky-300'],
            ['Owner & Admin', $overview['stats']['staff'], 'text-orange-300'],
            ['Nasabah', $overview['stats']['borrowers'], 'text-violet-300'],
        ] as [$label, $value, $color])
            <article class="rounded-xl border border-slate-800 bg-[#101829] p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-bold text-white">{{ number_format($value, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs {{ $color }}">Data hanya dari koperasi ini</p>
            </article>
        @endforeach
    </section>

    @include('platform.partials.accounts-group', $overview)
@endsection
