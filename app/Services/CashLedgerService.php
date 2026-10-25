<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashLedgerService
{
    public function post(
        User $actor,
        string $type,
        ?string $source,
        string $direction,
        int $amount,
        ?int $loanId = null,
        ?string $reference = null,
        ?string $description = null,
    ): CashTransaction {
        if ($amount <= 0 || ! in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Transaksi kas tidak valid.');
        }

        DB::table('cash_accounts')->insertOrIgnore([
            'id' => 1,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $account = DB::table('cash_accounts')->where('id', 1)->lockForUpdate()->first();

        if (! $account) {
            throw new RuntimeException('Akun kas tidak tersedia.');
        }

        $balance = (int) $account->balance + ($direction === 'in' ? $amount : -$amount);

        DB::table('cash_accounts')->where('id', 1)->update([
            'balance' => $balance,
            'updated_at' => now(),
        ]);

        return CashTransaction::create([
            'created_by' => $actor->id,
            'type' => $type,
            'source' => $source,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
            'loan_id' => $loanId,
            'reference' => $reference,
            'description' => $description,
        ]);
    }

    public function balance(): int
    {
        return (int) (DB::table('cash_accounts')->where('id', 1)->value('balance') ?? 0);
    }
}
