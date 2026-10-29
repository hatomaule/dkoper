<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrower_advance_transactions', function (Blueprint $table) {
            $table->index('payment_id');
            $table->dropUnique(['payment_id']);
            $table->bigInteger('amount_delta')->nullable()->after('amount');
            $table->text('description')->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        if (DB::table('borrower_advance_transactions')->whereNotNull('amount_delta')->exists()
            || DB::table('borrower_advance_transactions')->select('payment_id')->groupBy('payment_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Riwayat koreksi uang muka sudah tersimpan; migrasi tidak dapat dibatalkan tanpa menghapus riwayat.');
        }

        Schema::table('borrower_advance_transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_id']);
            $table->unique('payment_id');
            $table->dropColumn(['amount_delta', 'description']);
        });
    }
};
