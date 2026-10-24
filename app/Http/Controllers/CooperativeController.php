<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\BorrowerService;
use App\Services\LoanService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $this->authorizeStaff();
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'unique:borrowers,phone', 'unique:users,phone'],
        ]);
        $borrower = $service->createWithAccount($validated['full_name'], $validated['phone']);
        return redirect()->route('cooperative.borrowers')->with('status', "Nasabah {$borrower->borrower_code} dan akun login berhasil dibuat.");
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
        $loan = $service->createApprovedLoan($request->user(), (int) $validated['borrower_id'], (int) $validated['principal_amount']);
        return redirect()->route('cooperative.loans')->with('status', "Pinjaman {$loan->loan_code} berhasil dicatat.");
    }

    public function payments(): View
    {
        $this->authorizeStaff();
        return view('cooperative.payments', ['loans' => Loan::with(['borrower', 'installments'])->whereIn('status', ['active', 'pending'])->latest()->get(), 'payments' => Payment::with(['loan.borrower'])->latest()->get()]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $this->authorizeStaff();
        $validated = $request->validate(['loan_id' => ['required', 'exists:loans,id'], 'installment_id' => ['nullable', 'exists:installments,id'], 'amount' => ['required', 'integer', 'min:1'], 'nominal_source' => ['required', 'in:system,manual']]);

        $payment = Payment::create(['loan_id' => $validated['loan_id'], 'installment_id' => $validated['installment_id'] ?? null, 'recorded_by' => $request->user()->id, 'amount' => $validated['amount'], 'nominal_source' => $validated['nominal_source'], 'status' => $request->user()->isManager() ? 'approved' : 'pending']);

        if ($request->user()->isManager()) {
            app(PaymentService::class)->approvePayment($request->user(), $payment->id);
            $message = 'Pembayaran berhasil dicatat dan disetujui.';
        } else {
            ApprovalRequest::create(['request_type' => 'payment', 'requested_by' => $request->user()->id, 'approvable_type' => Payment::class, 'approvable_id' => $payment->id, 'payload' => $validated, 'status' => 'pending']);
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
        if (! $request->user()->isManager()) abort(403);

        $approval = ApprovalRequest::where('approvable_type', Payment::class)
            ->where('approvable_id', $payment->id)
            ->where('status', 'pending')
            ->first();

        $service->approvePayment($request->user(), $payment->id);

        if ($approval) {
            $approval->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        }

        return redirect()->route('cooperative.approvals')->with('status', 'Pembayaran berhasil disetujui.');
    }

    public function rejectPayment(Request $request, Payment $payment): RedirectResponse
    {
        if (! $request->user()->isManager()) abort(403);
        $payment->update(['status' => 'rejected']);
        ApprovalRequest::where('approvable_type', Payment::class)
            ->where('approvable_id', $payment->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        return redirect()->route('cooperative.approvals')->with('status', 'Pembayaran ditolak.');
    }

    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()?->isManager() || auth()->user()?->isCashier(), 403);
    }
}
