<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\Borrower;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        if ($user->isBorrower()) {
            $borrower = Borrower::query()
                ->with(['loans' => fn ($query) => $query->whereIn('status', ['active', 'pending', 'paid'])->with('installments')])
                ->where('user_id', $user->id)
                ->firstOrFail();

            return view('borrower-dashboard', [
                'borrower' => $borrower,
                'advance_balance' => $borrower->advanceTransactions()->latest('id')->value('balance_after') ?? 0,
            ]);
        }

        $activeLoans = Loan::query()->where('status', 'active');

        $data = [
            'total_principal_running' => (clone $activeLoans)->sum('principal_amount'),
            'total_receivable_running' => (clone $activeLoans)->sum('total_receivable'),
            'total_payment_approved' => Installment::query()->whereHas('loan', fn ($query) => $query->where('status', 'active'))->sum('paid_amount'),
            'total_administration_fee' => (clone $activeLoans)->sum('administration_fee'),
            'cash_balance' => DB::table('cash_accounts')->where('id', 1)->value('balance') ?? CashTransaction::query()->latest('id')->value('balance_after') ?? 0,
            'alan_profit' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'alan'))->value('available_amount') ?? 0,
            'chalo_profit' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'chalo'))->value('available_amount') ?? 0,
            'alan_withdrawn' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'alan'))->value('withdrawn_amount') ?? 0,
            'chalo_withdrawn' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'chalo'))->value('withdrawn_amount') ?? 0,
            'my_profit' => ProfitBalance::query()->where('user_id', $user->id)->value('available_amount') ?? 0,
        ];

        $data['total_unpaid'] = (clone $activeLoans)->withSum('installments as outstanding_total', 'outstanding_amount')->get()->sum('outstanding_total');

        return view('dashboard', $data);
    }
}
