<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->unsignedBigInteger('interest_amount')->default(0)->after('scheduled_amount');
            $table->unsignedBigInteger('alan_profit_amount')->default(0)->after('interest_amount');
            $table->unsignedBigInteger('chalo_profit_amount')->default(0)->after('alan_profit_amount');
        });

        foreach (DB::table('loans')->get(['id', 'interest_amount']) as $loan) {
            $interest = (int) $loan->interest_amount;
            $alanProfit = (int) round($interest * 0.60);
            $chaloProfit = $interest - $alanProfit;
            $installments = DB::table('installments')
                ->where('loan_id', $loan->id)
                ->orderBy('sequence')
                ->get(['id']);
            $count = $installments->count();

            if ($count === 0) {
                continue;
            }

            foreach ($installments as $index => $installment) {
                DB::table('installments')->where('id', $installment->id)->update([
                    'interest_amount' => intdiv($interest, $count) + ($index < $interest % $count ? 1 : 0),
                    'alan_profit_amount' => intdiv($alanProfit, $count) + ($index < $alanProfit % $count ? 1 : 0),
                    'chalo_profit_amount' => intdiv($chaloProfit, $count) + ($index < $chaloProfit % $count ? 1 : 0),
                ]);
            }
        }

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
        });
        DB::table('cash_accounts')->insert([
            'id' => 1,
            'balance' => (int) (DB::table('cash_transactions')->latest('id')->value('balance_after') ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('borrower_advance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_advance_transactions');
        Schema::dropIfExists('cash_accounts');

        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn(['interest_amount', 'alan_profit_amount', 'chalo_profit_amount']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['rejected_by', 'rejected_at']);
        });
    }
};
