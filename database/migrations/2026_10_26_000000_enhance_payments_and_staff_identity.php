<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('username', 'owner')->exists() || DB::table('users')->where('username', 'admin')->exists()) {
            throw new RuntimeException('Username owner/admin sudah digunakan; selesaikan konflik akun sebelum migrasi.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method', 16)->default('cash')->after('nominal_source');
            $table->string('proof_path')->nullable()->after('payment_method');
        });

        DB::table('users')->where('username', 'alan')->update([
            'name' => 'Owner',
            'username' => 'owner',
        ]);
        DB::table('users')->where('username', 'chalo')->update([
            'name' => 'Admin',
            'username' => 'admin',
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'owner')->update([
            'name' => 'Alan (Manager)',
            'username' => 'alan',
        ]);
        DB::table('users')->where('username', 'admin')->update([
            'name' => 'Chalo (Kasir)',
            'username' => 'chalo',
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'proof_path']);
        });
    }
};
