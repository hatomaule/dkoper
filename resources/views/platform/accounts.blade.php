@extends('platform.layout')

@section('page-title', 'Manajemen akun')

@section('content')
    @if ($cooperatives->isEmpty())
        <div class="rounded-xl border border-slate-800 bg-[#101829] p-8 text-center">
            <p class="font-semibold text-white">Belum ada koperasi terdaftar.</p>
            <a href="{{ route('platform.cooperatives') }}" class="mt-3 inline-block text-sm font-semibold text-orange-300 hover:text-orange-200">Daftarkan koperasi</a>
        </div>
    @elseif ($overview)
        @include('platform.partials.accounts-group', $overview)
    @else
        <section class="rounded-xl border border-slate-800 bg-[#101829] p-6 text-sm text-slate-400">
            Koperasi yang dipilih tidak ditemukan. Pilih koperasi lain dari submenu di samping.
        </section>
    @endif
@endsection
