<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\CashTransaction;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\ProfitTransaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\BorrowerService;
use App\Services\CashLedgerService;
use App\Services\CooperativeNotificationService;
use App\Services\LoanService;
use App\Services\PaymentService;
use App\Services\ProfitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CooperativeController extends Controller
{
    public function borrowers(): View
    {
        $this->authorizeStaff();
        $perPage = $this->perPage(request());
        $query = Borrower::with('user')
            ->withCount(['loans', 'advanceTransactions'])
            ->customerAccount()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('cooperative.borrowers', [
            'borrowers' => $perPage === 'all' ? $query->get() : $query->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
        ]);
    }

    public function storeBorrower(Request $request, BorrowerService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager() || $request->user()->isCashier(), 403);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'unique:borrowers,phone', 'unique:users,phone'],
        ]);
        $borrower = $service->createWithAccount($request->user(), $validated['full_name'], $validated['phone']);
        app(CooperativeNotificationService::class)->notifyRoles(
            $request->user()->isCashier() ? ['manager'] : ['kasir'],
            'Akun nasabah baru',
            "{$request->user()->name} menambahkan akun nasabah {$borrower->full_name}.",
            route('cooperative.borrowers', [], false),
            $request->user()->id,
        );

        return redirect()->route('cooperative.borrowers')->with('status', "Nasabah {$borrower->borrower_code} dan akun login berhasil dibuat.");
    }

    public function resetBorrowerPassword(Request $request, Borrower $borrower): RedirectResponse
    {
        $this->authorizeStaff();
        abort_unless($borrower->user, 404);

        $borrower->user->forceFill([
            'password' => Hash::make('123456'),
            'must_change_password' => true,
        ])->save();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'borrower.password_reset',
            'entity_type' => Borrower::class,
            'entity_id' => $borrower->id,
            'metadata' => ['borrower_code' => $borrower->borrower_code],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('cooperative.borrowers')->with('status', "Password {$borrower->borrower_code} direset; wajib diganti saat login.");
    }

    public function updateBorrowerPassword(Request $request, Borrower $borrower): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        abort_unless($borrower->user, 404);
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $borrower->user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'borrower.password_changed',
            'entity_type' => Borrower::class,
            'entity_id' => $borrower->id,
            'metadata' => ['borrower_code' => $borrower->borrower_code],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('cooperative.borrowers')->with('status', "Password {$borrower->full_name} berhasil diperbarui.");
    }

    public function destroyBorrower(Request $request, Borrower $borrower): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        return DB::transaction(function () use ($request, $borrower) {
            $borrower = Borrower::query()->lockForUpdate()->findOrFail($borrower->id);

            if ($borrower->loans()->exists() || $borrower->advanceTransactions()->exists()) {
                return redirect()->route('cooperative.borrowers')
                    ->withErrors(['borrower' => 'Akun tidak dapat dihapus karena memiliki riwayat pinjaman atau transaksi.']);
            }

            $borrower->load('user');
            $borrowerName = $borrower->full_name;
            $borrowerCode = $borrower->borrower_code;
            $borrowerId = $borrower->id;

            $borrower->delete();
            $borrower->user?->delete();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'borrower.deleted',
                'entity_type' => Borrower::class,
                'entity_id' => $borrowerId,
                'metadata' => ['borrower_code' => $borrowerCode, 'full_name' => $borrowerName],
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('cooperative.borrowers')
                ->with('status', "Akun nasabah {$borrowerName} berhasil dihapus.");
        });
    }

    public function updateStaffPassword(Request $request, User $user): RedirectResponse
    {
        $isOwner = $request->user()->isManager();
        $isOwnAccount = $request->user()->id === $user->id;
        abort_unless($isOwner || $isOwnAccount, 403);
        abort_unless(in_array($user->username, ['owner', 'admin'], true) && ($user->isManager() || $user->isCashier()), 404);
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'staff.password_changed',
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'metadata' => ['username' => $user->username],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('status', "Password akun {$user->name} berhasil diperbarui.");
    }

    public function loans(): View
    {
        $this->authorizeStaff();
        $perPage = $this->perPage(request());
        $query = Loan::with(['borrower', 'installments', 'approver'])
            ->withCount('payments')
            ->whereIn('status', ['active', 'paid'])
            ->whereHas('borrower', fn ($query) => $query->customerAccount())
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('cooperative.loans', [
            'borrowers' => Borrower::with('user')
                ->customerAccount()
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(),
            'loans' => $perPage === 'all' ? $query->get() : $query->paginate($perPage)->withQueryString(),
            'loanTotals' => [
                'principal' => Loan::query()
                    ->whereIn('status', ['active', 'paid'])
                    ->whereHas('borrower', fn ($query) => $query->customerAccount())
                    ->sum('principal_amount'),
                'installments' => Installment::query()
                    ->whereHas('loan', fn ($loanQuery) => $loanQuery
                        ->whereIn('status', ['active', 'paid'])
                        ->whereHas('borrower', fn ($query) => $query->customerAccount()))
                    ->selectRaw('sequence, SUM(paid_amount) AS paid_total')
                    ->groupBy('sequence')
                    ->pluck('paid_total', 'sequence'),
            ],
            'perPage' => $perPage,
        ]);
    }

    public function storeLoan(Request $request, LoanService $service): RedirectResponse
    {
        $this->authorizeStaff();
        $validated = $request->validate(['borrower_id' => ['required', 'exists:borrowers,id'], 'principal_amount' => ['required', 'integer', 'min:1000']]);
        $eligibleBorrower = Borrower::query()
            ->customerAccount()
            ->whereKey($validated['borrower_id'])
            ->where('status', 'active')
            ->exists();
        if (! $eligibleBorrower) {
            throw \Illuminate\Validation\ValidationException::withMessages(['borrower_id' => 'Pilih akun nasabah yang aktif.']);
        }

        $loan = $service->createLoan($request->user(), (int) $validated['borrower_id'], (int) $validated['principal_amount']);
        $message = $loan->status === 'active'
            ? "Pinjaman {$loan->loan_code} berhasil disetujui dan dicatat."
            : "Pinjaman {$loan->loan_code} dikirim ke antrean persetujuan Owner.";

        return redirect()->route('cooperative.loans')->with('status', $message);
    }

    public function correctLoan(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate([
            'principal_amount' => ['required', 'integer', 'min:1000'],
            'administration_fee' => ['required', 'integer', 'min:0'],
            'interest_amount' => ['required', 'integer', 'min:0'],
            'installments' => ['required', 'array', 'size:12'],
            'installments.*' => ['required', 'integer', 'min:1'],
        ]);

        $service->correctLoan($request->user(), $loan->id, $validated);

        return redirect()->route('cooperative.loans')->with('status', "Pinjaman {$loan->loan_code} berhasil dikoreksi. Riwayat dan ledger tetap tersimpan.");
    }

    public function archiveLoan(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $service->archiveLoan($request->user(), $loan->id);

        return redirect()->route('cooperative.loans')->with('status', "Pinjaman {$loan->loan_code} diarsipkan; riwayat finansial tetap tersimpan.");
    }

    public function payments(): View
    {
        $this->authorizeStaff();
        $perPage = $this->perPage(request());

        $query = Payment::with(['loan.borrower', 'installment'])->orderByDesc('created_at')->orderByDesc('id');
        $payments = $perPage === 'all' ? $query->get() : $query->paginate($perPage)->withQueryString();
        $paymentLoans = Loan::with([
            'borrower',
            'installments' => fn ($query) => $query->where('outstanding_amount', '>', 0)->orderBy('sequence'),
        ])        ->where('status', 'active')
        ->whereHas('installments', fn ($query) => $query->where('outstanding_amount', '>', 0))
        ->whereHas('borrower', fn ($query) => $query->customerAccount()->where('status', 'active'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $correctionLoans = Loan::with(['borrower', 'installments' => fn ($query) => $query->orderBy('sequence')])
            ->whereIn('status', ['active', 'paid'])
            ->whereHas('borrower', fn ($query) => $query->customerAccount())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('cooperative.payments', [
            'paymentLoans' => $paymentLoans,
            'correctionLoans' => $correctionLoans,
            'payments' => $payments,
            'perPage' => $perPage,
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $this->authorizeStaff();
        $validated = $request->validate([
            'loan_id' => ['required', Rule::exists('loans', 'id')->where('status', 'active')],
            'installment_id' => ['required', 'integer'],
            'nominal_source' => ['required', 'in:system,manual'],
            'payment_method' => ['required', 'in:cash,transfer'],
            'amount' => ['required_if:nominal_source,manual', 'nullable', 'integer', 'min:1'],
            'proof' => ['required_if:payment_method,transfer', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $eligibleLoan = Loan::query()
            ->whereKey($validated['loan_id'])
            ->where('status', 'active')
            ->whereHas('borrower', fn ($query) => $query->customerAccount()->where('status', 'active'))
            ->exists();
        if (! $eligibleLoan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['loan_id' => 'Pilih pinjaman aktif milik nasabah.']);
        }

        $installment = Installment::query()
            ->where('loan_id', $validated['loan_id'])
            ->where('id', $validated['installment_id'])
            ->where('outstanding_amount', '>', 0)
            ->firstOrFail();
        if ($validated['nominal_source'] === 'system') {
            $validated['amount'] = (int) $installment->outstanding_amount;
        }

        if ($validated['payment_method'] === 'cash' && $request->hasFile('proof')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['proof' => 'Bukti hanya dapat dilampirkan untuk pembayaran transfer.']);
        }

        $proofPath = $request->hasFile('proof')
            ? $request->file('proof')->store(
                config('fides.enabled')
                    ? 'cooperatives/'.(int) config('fides.current_tenant_id').'/payment-proofs'
                    : 'payment-proofs',
                'local',
            )
            : null;

        $payment = Payment::create([
            'loan_id' => $validated['loan_id'],
            'installment_id' => $installment->id,
            'recorded_by' => $request->user()->id,
            'amount' => $validated['amount'],
            'nominal_source' => $validated['nominal_source'],
            'payment_method' => $validated['payment_method'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        if ($request->user()->isManager()) {
            app(PaymentService::class)->approvePayment($request->user(), $payment->id);
            app(CooperativeNotificationService::class)->notifyRoles(
                ['kasir'],
                'Setoran dicatat Owner',
                "Owner mencatat setoran Rp ".number_format((int) $payment->amount, 0, ',', '.').'.',
                route('cooperative.payments', [], false),
                $request->user()->id,
            );
            $message = 'Pembayaran berhasil dicatat dan disetujui.';
        } else {
            ApprovalRequest::create(['request_type' => 'payment', 'requested_by' => $request->user()->id, 'approvable_type' => Payment::class, 'approvable_id' => $payment->id, 'payload' => ['amount' => $payment->amount, 'loan_id' => $payment->loan_id, 'payment_method' => $payment->payment_method], 'status' => 'pending']);
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'payment.recorded',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'metadata' => ['amount' => $payment->amount, 'loan_id' => $payment->loan_id],
                'ip_address' => $request->ip(),
            ]);
            app(CooperativeNotificationService::class)->notifyRoles(
                ['manager'],
                'Setoran menunggu persetujuan',
                "Admin mencatat setoran Rp ".number_format((int) $payment->amount, 0, ',', '.').'.',
                route('cooperative.approvals', [], false),
            );
            $message = 'Pembayaran dikirim ke antrean persetujuan Owner.';
        }

        return redirect()->route('cooperative.payments')->with('status', $message);
    }

    public function paymentProof(Request $request, Payment $payment)
    {
        $this->authorizeStaff();
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return response()->file(Storage::disk('local')->path($payment->proof_path), [
            'Content-Type' => Storage::disk('local')->mimeType($payment->proof_path),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatePayment(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'installment_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);
        $targetLoan = Loan::query()->findOrFail($validated['loan_id']);
        $eligibleBorrower = $targetLoan->borrower()->customerAccount()
            ->exists();
        if (! in_array($targetLoan->status, ['active', 'paid'], true)
            || ! $eligibleBorrower
            || ! Installment::query()->where('loan_id', $targetLoan->id)->whereKey($validated['installment_id'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['installment_id' => 'Pilih cicilan yang sesuai dengan kontrak pinjaman.']);
        }

        $service->correctApprovedPayment(
            $request->user(),
            $payment->id,
            (int) $validated['amount'],
            (int) $targetLoan->id,
            (int) $validated['installment_id'],
        );
        app(CooperativeNotificationService::class)->notifyBorrower(
            $targetLoan->borrower,
            'Data setoran diperbarui',
            "Owner memperbarui data setoran pinjaman {$targetLoan->loan_code}.",
            route('dashboard', [], false),
        );

        return redirect()->route('cooperative.payments')->with('status', 'Nominal setoran berhasil dikoreksi.');
    }

    public function approvals(): View
    {
        abort_unless(auth()->user()?->isManager(), 403);
        $perPage = $this->perPage(request());
        $query = ApprovalRequest::with(['requester', 'approvable'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('cooperative.approvals', [
            'approvalRequests' => $perPage === 'all' ? $query->get() : $query->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
        ]);
    }

    public function approvePayment(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $service->approvePayment($request->user(), $payment->id);

        return redirect()->route('cooperative.approvals')->with('status', 'Pembayaran berhasil disetujui.');
    }

    public function rejectPayment(Request $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $service->rejectPayment($request->user(), $payment->id, trim($validated['reason']));
        return redirect()->route('cooperative.approvals')->with('status', 'Pembayaran ditolak.');
    }

    public function approveLoan(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $service->approveLoan($request->user(), $loan->id);

        return redirect()->route('cooperative.approvals')->with('status', "Pinjaman {$loan->loan_code} berhasil disetujui.");
    }

    public function rejectLoan(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $service->rejectLoan($request->user(), $loan->id, trim($validated['reason']));

        return redirect()->route('cooperative.approvals')->with('status', "Pinjaman {$loan->loan_code} ditolak.");
    }

    public function cashHistory(Request $request, CashLedgerService $service): View
    {
        abort_unless($request->user()->isManager(), 403);
        $now = now('Asia/Singapore');
        $chartStart = $now->copy()->subMonths(11)->startOfMonth();
        $chartEnd = $now->copy()->endOfMonth();
        $cashRows = CashTransaction::query()
            ->whereBetween('created_at', [$chartStart->copy()->utc(), $chartEnd->copy()->utc()])
            ->get(['amount', 'direction', 'created_at']);
        $monthlyCashFlow = collect(range(11, 0))->map(function (int $monthsAgo) use ($now, $cashRows) {
            $month = $now->copy()->subMonths($monthsAgo)->startOfMonth();
            $transactions = $cashRows->filter(
                fn (CashTransaction $transaction) => $transaction->created_at->copy()->timezone('Asia/Singapore')->format('Y-m') === $month->format('Y-m'),
            );

            return [
                'label' => $month->translatedFormat('M'),
                'in' => (int) $transactions->where('direction', 'in')->sum('amount'),
                'out' => (int) $transactions->where('direction', 'out')->sum('amount'),
            ];
        });

        return view('cooperative.finances', [
            'balance' => $service->balance(),
            'monthlyCashFlow' => $monthlyCashFlow,
            'maxMonthlyCashFlow' => max(1, (int) $monthlyCashFlow->max(fn ($month) => $month['in'] + $month['out'])),
            'transactions' => $this->paginated(CashTransaction::with(['creator', 'loan.borrower'])->orderByDesc('created_at')->orderByDesc('id'), $request),
        ]);
    }

    public function storeCashAdjustment(Request $request, CashLedgerService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $transaction = $service->post(
            $request->user(),
            'cash_adjustment',
            'manual',
            $validated['direction'],
            (int) $validated['amount'],
            description: $validated['description'],
        );

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'cash.adjusted',
            'entity_type' => CashTransaction::class,
            'entity_id' => $transaction->id,
            'metadata' => ['direction' => $transaction->direction, 'amount' => $transaction->amount],
            'ip_address' => $request->ip(),
        ]);
        app(CooperativeNotificationService::class)->notifyRoles(
            ['kasir'],
            'Perubahan kas dicatat',
            'Owner mencatat transaksi perubahan kas Rp '.number_format((int) $transaction->amount, 0, ',', '.').'.',
            route('cooperative.finances', [], false),
        );

        return redirect()->route('cooperative.finances')->with('status', 'Transaksi kas berhasil dicatat.');
    }

    public function profits(Request $request): View
    {
        abort_unless($request->user()->isManager() || $request->user()->isCashier(), 403);

        $projectedBonus = Installment::query()
            ->whereHas('loan', fn ($query) => $query->where('status', 'active'))
            ->where('outstanding_amount', '>', 0)
            ->selectRaw('SUM(alan_profit_amount) AS owner_total, SUM(chalo_profit_amount) AS admin_total')
            ->first();

        return view('cooperative.profits', [
            'balances' => ProfitBalance::with('user')
                ->whereHas('user', fn ($query) => $query->whereIn('role', ['manager', 'kasir']))
                ->orderBy('user_id')
                ->get()
                ->each(function (ProfitBalance $balance) use ($projectedBonus): void {
                    $balance->projected_amount = (int) ($balance->user->isManager()
                        ? ($projectedBonus->owner_total ?? 0)
                        : ($projectedBonus->admin_total ?? 0));
                }),
            'transactions' => $this->paginated(ProfitTransaction::with(['user', 'creator'])->orderByDesc('created_at')->orderByDesc('id'), $request),
        ]);
    }

    public function withdrawProfit(Request $request, User $user, ProfitService $service): RedirectResponse
    {
        $canManageAny = $request->user()->isManager();
        $canSelf = $request->user()->id === $user->id;
        abort_unless($canManageAny || $canSelf, 403);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $service->withdraw($request->user(), $user->id, (int) $validated['amount']);
        app(CooperativeNotificationService::class)->notifyRoles(
            $request->user()->isManager() ? ['kasir'] : ['manager'],
            'Penarikan bonus dicatat',
            "{$request->user()->name} mencatat penarikan bonus Rp ".number_format((int) $validated['amount'], 0, ',', '.').'.',
            route('cooperative.profits', [], false),
            $request->user()->id,
        );

        return redirect()->route('cooperative.profits')->with('status', 'Penarikan bonus berhasil dicatat.');
    }

    public function transferProfit(Request $request, User $user, ProfitService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        abort_unless($user->isManager(), 403, 'Bonus Admin tidak dapat dipindahkan ke modal.');
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $service->transferToCapital($request->user(), $user->id, (int) $validated['amount']);
        app(CooperativeNotificationService::class)->notifyRoles(
            ['kasir'],
            'Bonus dipindahkan ke modal',
            'Owner memindahkan bonus ke modal sebesar Rp '.number_format((int) $validated['amount'], 0, ',', '.').'.',
            route('cooperative.profits', [], false),
        );

        return redirect()->route('cooperative.profits')->with('status', 'Transfer bonus ke modal berhasil dicatat.');
    }

    public function notifications(Request $request): View
    {
        $perPage = $this->perPage($request);
        $query = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('cooperative.notifications', [
            'notifications' => $perPage === 'all' ? $query->get() : $query->paginate($perPage)->withQueryString(),
            'perPage' => $perPage,
            'unreadCount' => UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    public function unreadNotifications(Request $request)
    {
        return response()->json([
            'unread_count' => UserNotification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function openNotification(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);
        abort_unless(str_starts_with($notification->url, '/') && ! str_starts_with($notification->url, '//'), 404);
        $notification->forceFill(['read_at' => now()])->save();

        return redirect()->to($notification->url);
    }

    public function markNotificationRead(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);
        $notification->forceFill(['read_at' => now()])->save();

        return redirect()->route('cooperative.notifications');
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->route('cooperative.notifications');
    }

    public function activityLogs(Request $request): View
    {
        abort_unless($request->user()->isManager() || $request->user()->isCashier(), 403);

        return view('cooperative.activity-logs', [
            'logs' => $this->paginated(ActivityLog::with('user')->orderByDesc('created_at')->orderByDesc('id'), $request),
        ]);
    }

    private function perPage(Request $request): int|string
    {
        $size = $request->query('per_page', '5');
        abort_unless(in_array($size, ['5', '10', '25', '50', 'all'], true), 422);

        return $size === 'all' ? 'all' : (int) $size;
    }

    private function paginated($query, Request $request)
    {
        $perPage = $this->perPage($request);

        return $perPage === 'all' ? $query->get() : $query->paginate($perPage)->withQueryString();
    }

    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()?->isManager() || auth()->user()?->isCashier(), 403);
    }
}
