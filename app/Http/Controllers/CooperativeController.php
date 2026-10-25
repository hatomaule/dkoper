<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\ProfitTransaction;
use App\Models\User;
use App\Services\BorrowerService;
use App\Services\CashLedgerService;
use App\Services\LoanService;
use App\Services\PaymentService;
use App\Services\ProfitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CooperativeController extends Controller
{
    public function borrowers(): View
    {
        $this->authorizeStaff();
        return view('cooperative.borrowers', ['borrowers' => Borrower::with('loans')->latest()->get()]);
    }

    public function storeBorrower(Request $request, BorrowerService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'unique:borrowers,phone', 'unique:users,phone'],
        ]);
        $borrower = $service->createWithAccount($request->user(), $validated['full_name'], $validated['phone']);
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

    public function loans(): View
    {
        $this->authorizeStaff();
        return view('cooperative.loans', ['borrowers' => Borrower::with('user')->orderBy('full_name')->get(), 'loans' => Loan::with('borrower')->latest()->get()]);
    }

    public function storeLoan(Request $request, LoanService $service): RedirectResponse
    {
        $this->authorizeStaff();
        $validated = $request->validate(['borrower_id' => ['required', 'exists:borrowers,id'], 'principal_amount' => ['required', 'integer', 'min:1000']]);
        $loan = $service->createLoan($request->user(), (int) $validated['borrower_id'], (int) $validated['principal_amount']);
        $message = $loan->status === 'active'
            ? "Pinjaman {$loan->loan_code} berhasil disetujui dan dicatat."
            : "Pinjaman {$loan->loan_code} dikirim ke antrean approval Manager.";

        return redirect()->route('cooperative.loans')->with('status', $message);
    }

    public function payments(): View
    {
        $this->authorizeStaff();
        return view('cooperative.payments', ['loans' => Loan::with(['borrower', 'installments'])->where('status', 'active')->latest()->get(), 'payments' => Payment::with(['loan.borrower'])->latest()->paginate(30)]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $this->authorizeStaff();
        $validated = $request->validate([
            'loan_id' => ['required', Rule::exists('loans', 'id')->where('status', 'active')],
            'amount' => ['required', 'integer', 'min:1'],
            'nominal_source' => ['required', 'in:system,manual'],
        ]);

        $payment = Payment::create([
            'loan_id' => $validated['loan_id'],
            'recorded_by' => $request->user()->id,
            'amount' => $validated['amount'],
            'nominal_source' => $validated['nominal_source'],
            'status' => 'pending',
        ]);

        if ($request->user()->isManager()) {
            app(PaymentService::class)->approvePayment($request->user(), $payment->id);
            $message = 'Pembayaran berhasil dicatat dan disetujui.';
        } else {
            ApprovalRequest::create(['request_type' => 'payment', 'requested_by' => $request->user()->id, 'approvable_type' => Payment::class, 'approvable_id' => $payment->id, 'payload' => $validated, 'status' => 'pending']);
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'payment.recorded',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'metadata' => ['amount' => $payment->amount, 'loan_id' => $payment->loan_id],
                'ip_address' => $request->ip(),
            ]);
            $message = 'Pembayaran dikirim ke antrean approval Manager.';
        }

        return redirect()->route('cooperative.payments')->with('status', $message);
    }

    public function approvals(): View
    {
        abort_unless(auth()->user()?->isManager(), 403);
        return view('cooperative.approvals', [
            'approvalRequests' => ApprovalRequest::with(['requester', 'approvable'])->where('status', 'pending')->latest()->get(),
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
        $service->rejectPayment($request->user(), $payment->id);
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
        $service->rejectLoan($request->user(), $loan->id);

        return redirect()->route('cooperative.approvals')->with('status', "Pinjaman {$loan->loan_code} ditolak.");
    }

    public function cashHistory(Request $request, CashLedgerService $service): View
    {
        abort_unless($request->user()->isManager(), 403);

        return view('cooperative.finances', [
            'balance' => $service->balance(),
            'transactions' => CashTransaction::with(['creator', 'loan.borrower'])->latest()->paginate(30),
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

        return redirect()->route('cooperative.finances')->with('status', 'Transaksi kas berhasil dicatat.');
    }

    public function profits(Request $request): View
    {
        abort_unless($request->user()->isManager(), 403);

        return view('cooperative.profits', [
            'balances' => ProfitBalance::with('user')->orderBy('user_id')->get(),
            'transactions' => ProfitTransaction::with(['user', 'creator'])->latest()->paginate(30),
        ]);
    }

    public function withdrawProfit(Request $request, User $user, ProfitService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $service->withdraw($request->user(), $user->id, (int) $validated['amount']);

        return redirect()->route('cooperative.profits')->with('status', 'Penarikan bonus berhasil dicatat.');
    }

    public function transferProfit(Request $request, User $user, ProfitService $service): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $service->transferToCapital($request->user(), $user->id, (int) $validated['amount']);

        return redirect()->route('cooperative.profits')->with('status', 'Transfer bonus ke modal berhasil dicatat.');
    }

    public function activityLogs(Request $request): View
    {
        abort_unless($request->user()->isManager(), 403);

        return view('cooperative.activity-logs', [
            'logs' => ActivityLog::with('user')->latest()->paginate(50),
        ]);
    }

    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()?->isManager() || auth()->user()?->isCashier(), 403);
    }
}
