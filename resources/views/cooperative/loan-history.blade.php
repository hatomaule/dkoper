<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-slate-900">Riwayat Pinjaman</h2></x-slot>
    <div class="min-h-[calc(100vh-65px)] bg-slate-100 px-4 py-8">
        <div class="mx-auto max-w-7xl space-y-5">
            @if (session('status'))<div class="dk-form-success">{{ session('status') }}</div>@endif
            <section class="dk-data-panel">
                <div class="dk-loan-history-heading">
                    <div>
                        <p class="dk-eyebrow">DATA TERSIMPAN</p>
                        <h3>Pinjaman yang dihapus dari daftar</h3>
                        <p>Data pembayaran, log aktivitas, dan laporan keuangan tetap utuh.</p>
                    </div>
                    <a href="{{ route('cooperative.loans') }}" class="dk-button-secondary">Kembali ke Pinjaman</a>
                </div>
                <div class="dk-loan-history-list">
                    @forelse ($loans as $loan)
                        <article class="dk-loan-history-card">
                            <div class="dk-loan-history-title">
                                <div><strong>{{ $loan->borrower->full_name }}</strong><small>{{ $loan->loan_code }}</small></div>
                                <span>{{ $loan->deleted_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <dl>
                                <div><dt>Status terakhir</dt><dd>{{ match ($loan->status) { 'pending' => 'Menunggu', 'active' => 'Aktif', 'paid' => 'Lunas', 'rejected' => 'Ditolak', default => ucfirst($loan->status) } }}</dd></div>
                                <div><dt>Pokok</dt><dd>Rp {{ number_format((int) $loan->principal_amount, 0, ',', '.') }}</dd></div>
                                <div><dt>Total tagihan</dt><dd>Rp {{ number_format((int) $loan->total_receivable, 0, ',', '.') }}</dd></div>
                                <div><dt>Total setoran</dt><dd>Rp {{ number_format((int) $loan->installments->sum('paid_amount'), 0, ',', '.') }}</dd></div>
                                <div><dt>Sisa piutang</dt><dd>Rp {{ number_format((int) $loan->installments->sum('outstanding_amount'), 0, ',', '.') }}</dd></div>
                            </dl>
                        </article>
                    @empty
                        <p class="dk-loan-history-empty">Belum ada pinjaman yang dihapus.</p>
                    @endforelse
                </div>
                @if ($loans instanceof \Illuminate\Contracts\Pagination\Paginator)
                    <div class="dk-table-footer">{{ $loans->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
