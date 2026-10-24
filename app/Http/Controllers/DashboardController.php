<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ProfitBalance;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeLoans = Loan::query()->where('status', 'active');

        $data = [
            'total_principal_running' => (clone $activeLoans)->sum('principal_amount'),
            'total_receivable_running' => (clone $activeLoans)->sum('total_receivable'),
            'total_payment_approved' => Payment::query()->where('status', 'approved')->sum('amount'),
            'total_administration_fee' => (clone $activeLoans)->sum('administration_fee'),
            'cash_balance' => CashTransaction::query()->latest('id')->value('balance_after') ?? 0,
            'alan_profit' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'alan'))->value('available_amount') ?? 0,
            'chalo_profit' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'chalo'))->value('available_amount') ?? 0,
            'alan_withdrawn' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'alan'))->value('withdrawn_amount') ?? 0,
            'chalo_withdrawn' => ProfitBalance::query()->whereHas('user', fn ($query) => $query->where('username', 'chalo'))->value('withdrawn_amount') ?? 0,
        ];

        $data['total_unpaid'] = $data['total_receivable_running'] - $data['total_payment_approved'];

        return view('dashboard', $data);
    }
}
