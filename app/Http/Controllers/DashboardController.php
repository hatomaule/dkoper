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
                ->with(['loans' => fn ($query) => $query->whereIn('status', ['active', 'pending', 'paid'])
                    ->with(['installments' => fn ($installments) => $installments->orderByDesc('sequence')])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')])
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
        $weekStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $monthCashTransactions = CashTransaction::query()
            ->whereBetween('created_at', [$weekStart->copy()->utc(), $monthEnd->copy()->utc()])
            ->get(['amount', 'direction', 'created_at']);
        $weeklyCashFlow = collect();
        for ($weekNumber = 1, $cursor = $weekStart->copy(); $cursor->lte($monthEnd); $weekNumber++) {
            $periodEnd = $cursor->copy()->addDays(6)->endOfDay()->min($monthEnd);
            $transactions = $monthCashTransactions->filter(function (CashTransaction $transaction) use ($cursor, $periodEnd) {
                $date = $transaction->created_at->copy()->timezone('Asia/Singapore');

                return $date->gte($cursor) && $date->lte($periodEnd);
            });
            $weeklyCashFlow->push([
                'label' => "Minggu {$weekNumber}",
                'debit' => (int) $transactions->where('direction', 'in')->sum('amount'),
                'credit' => (int) $transactions->where('direction', 'out')->sum('amount'),
            ]);
            $cursor = $periodEnd->copy()->addSecond()->startOfDay();
        }

        $chartStart = $now->copy()->subMonths(11)->startOfMonth();
        $monthlyTransactionRows = CashTransaction::query()
            ->whereBetween('created_at', [$chartStart->copy()->utc(), $monthEnd->copy()->utc()])
            ->get(['amount', 'created_at']);
        $monthlyTransactionAmounts = collect(range(11, 0))->map(function (int $monthsAgo) use ($now, $monthlyTransactionRows) {
            $month = $now->copy()->subMonths($monthsAgo)->startOfMonth();
            $amount = $monthlyTransactionRows
                ->filter(fn (CashTransaction $transaction) => $transaction->created_at->copy()->timezone('Asia/Singapore')->format('Y-m') === $month->format('Y-m'))
                ->sum('amount');

            return ['label' => $month->translatedFormat('M'), 'amount' => (int) $amount];
        });
        $maxMonthlyTransactionAmount = max(1, (int) $monthlyTransactionAmounts->max('amount'));
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();
        $reportWeekStart = $now->copy()->startOfWeek();
        $reportWeekEnd = $now->copy()->endOfWeek();
        $monthStart = $now->copy()->startOfMonth();
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
            'owner_earned_bonus' => $owner?->profitBalance?->earned_amount ?? 0,
            'admin_earned_bonus' => $admin?->profitBalance?->earned_amount ?? 0,
            'owner_future_bonus' => (clone $futureInstallments)->sum('alan_profit_amount'),
            'admin_future_bonus' => (clone $futureInstallments)->sum('chalo_profit_amount'),
            'my_profit' => ProfitBalance::query()->where('user_id', $user->id)->value('available_amount') ?? 0,
            'borrower_count' => Borrower::query()->count(),
            'owner_name' => $owner?->name ?? 'Owner',
            'admin_name' => $admin?->name ?? 'Admin',
            'weekly_cash_flow' => $weeklyCashFlow,
            'monthly_transaction_amounts' => $monthlyTransactionAmounts,
            'max_monthly_transaction_amount' => $maxMonthlyTransactionAmount,
            'daily_payments' => Payment::query()->where('status', 'approved')->whereBetween('created_at', [$dayStart->utc(), $dayEnd->utc()])->sum('amount'),
            'weekly_payments' => Payment::query()->where('status', 'approved')->whereBetween('created_at', [$reportWeekStart->utc(), $reportWeekEnd->utc()])->sum('amount'),
            'monthly_total_payments' => Payment::query()->where('status', 'approved')->whereBetween('created_at', [$monthStart->utc(), $monthEnd->utc()])->sum('amount'),
        ];

        $data['total_unpaid'] = (clone $activeLoans)->withSum('installments as outstanding_total', 'outstanding_amount')->get()->sum('outstanding_total');

        return view('dashboard', $data);
    }
}
