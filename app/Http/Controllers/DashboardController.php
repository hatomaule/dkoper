<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use App\Models\User;
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
        $owner = User::query()->where('username', 'owner')->with('profitBalance')->first();
        $admin = User::query()->where('username', 'admin')->with('profitBalance')->first();
        $now = now('Asia/Singapore');
        $chartStart = $now->copy()->subMonths(5)->startOfMonth();
        $chartEnd = $now->copy()->endOfMonth();
        $payments = Payment::query()
            ->where('status', 'approved')
            ->whereBetween('created_at', [$chartStart->copy()->utc(), $chartEnd->copy()->utc()])
            ->get(['amount', 'created_at']);
        $monthlyPayments = collect(range(5, 0))->map(function (int $monthsAgo) use ($now, $payments) {
            $month = $now->copy()->subMonths($monthsAgo)->startOfMonth();
            $amount = $payments->filter(fn ($payment) => $payment->created_at->copy()->timezone('Asia/Singapore')->format('Y-m') === $month->format('Y-m'))->sum('amount');

            return ['label' => $month->translatedFormat('M'), 'amount' => (int) $amount];
        });
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $futureInstallments = Installment::query()
            ->whereHas('loan', fn ($query) => $query->where('status', 'active'))
            ->where('outstanding_amount', '>', 0);

        $data = [
            'total_principal_running' => (clone $activeLoans)->sum('principal_amount'),
            'total_receivable_running' => (clone $activeLoans)->sum('total_receivable'),
            'total_payment_approved' => Installment::query()->whereHas('loan', fn ($query) => $query->where('status', 'active'))->sum('paid_amount'),
            'total_administration_fee' => (clone $activeLoans)->sum('administration_fee'),
            'cash_balance' => DB::table('cash_accounts')->where('id', 1)->value('balance') ?? CashTransaction::query()->latest('id')->value('balance_after') ?? 0,
            'owner_profit' => $owner?->profitBalance?->available_amount ?? 0,
            'admin_profit' => $admin?->profitBalance?->available_amount ?? 0,
            'owner_future_bonus' => (clone $futureInstallments)->sum('alan_profit_amount'),
            'admin_future_bonus' => (clone $futureInstallments)->sum('chalo_profit_amount'),
            'my_profit' => ProfitBalance::query()->where('user_id', $user->id)->value('available_amount') ?? 0,
            'borrower_count' => Borrower::query()->count(),
            'owner_name' => $owner?->name ?? 'Owner',
            'admin_name' => $admin?->name ?? 'Admin',
            'monthly_payments' => $monthlyPayments,
            'daily_payments' => Payment::query()->where('status', 'approved')->whereBetween('created_at', [$dayStart->utc(), $dayEnd->utc()])->sum('amount'),
            'weekly_payments' => Payment::query()->where('status', 'approved')->whereBetween('created_at', [$weekStart->utc(), $weekEnd->utc()])->sum('amount'),
            'monthly_total_payments' => Payment::query()->where('status', 'approved')->whereBetween('created_at', [$monthStart->utc(), $monthEnd->utc()])->sum('amount'),
        ];

        $data['total_unpaid'] = (clone $activeLoans)->withSum('installments as outstanding_total', 'outstanding_amount')->get()->sum('outstanding_total');

        return view('dashboard', $data);
    }
}
