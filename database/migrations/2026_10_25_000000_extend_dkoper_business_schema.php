<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->unique()->after('username');
            $table->string('role')->default('nasabah')->after('phone');
            $table->boolean('must_change_password')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        Schema::table('borrowers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete()->after('id');
            $table->string('borrower_code')->unique()->after('user_id');
            $table->string('full_name')->after('borrower_code');
            $table->string('phone')->nullable()->index()->after('full_name');
            $table->string('address')->nullable()->after('phone');
            $table->string('status')->default('active')->index()->after('address');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('borrower_id')->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->after('borrower_id')->constrained('users')->restrictOnDelete();
            $table->string('loan_code')->unique()->after('created_by');
            $table->unsignedBigInteger('principal_amount')->after('loan_code');
            $table->unsignedBigInteger('administration_fee')->after('principal_amount');
            $table->unsignedBigInteger('net_disbursement')->after('administration_fee');
            $table->unsignedBigInteger('interest_amount')->after('net_disbursement');
            $table->unsignedBigInteger('total_receivable')->after('interest_amount');
            $table->unsignedTinyInteger('installment_count')->default(12)->after('total_receivable');
            $table->string('status')->default('pending')->index()->after('installment_count');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->foreignId('loan_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence')->after('loan_id');
            $table->unsignedBigInteger('scheduled_amount')->after('sequence');
            $table->unsignedBigInteger('paid_amount')->default(0)->after('scheduled_amount');
            $table->unsignedBigInteger('outstanding_amount')->default(0)->after('paid_amount');
            $table->string('status')->default('pending')->index()->after('outstanding_amount');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->unique(['loan_id', 'sequence']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('loan_id')->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->nullable()->after('loan_id')->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->after('installment_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount')->after('approved_by');
            $table->string('nominal_source')->default('system')->after('amount');
            $table->string('status')->default('pending')->index()->after('nominal_source');
            $table->timestamp('approved_at')->nullable()->after('status');
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->string('request_type')->index()->after('id');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete()->after('request_type');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('requested_by');
            $table->nullableMorphs('approvable');
            $table->json('payload')->nullable()->after('approvable_type');
            $table->string('status')->default('pending')->index()->after('payload');
            $table->timestamp('reviewed_at')->nullable()->after('status');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->after('id');
            $table->string('type')->index()->after('created_by');
            $table->string('source')->nullable()->index()->after('type');
            $table->string('direction')->after('source');
            $table->bigInteger('amount')->after('direction');
            $table->bigInteger('balance_after')->default(0)->after('amount');
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete()->after('balance_after');
            $table->string('reference')->nullable()->unique()->after('loan_id');
            $table->text('description')->nullable()->after('reference');
        });

        Schema::table('profit_balances', function (Blueprint $table) {
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete()->after('id');
            $table->bigInteger('earned_amount')->default(0)->after('user_id');
            $table->bigInteger('withdrawn_amount')->default(0)->after('earned_amount');
            $table->bigInteger('transferred_to_modal_amount')->default(0)->after('withdrawn_amount');
            $table->bigInteger('available_amount')->default(0)->after('transferred_to_modal_amount');
        });

        Schema::table('profit_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->after('user_id');
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete()->after('created_by');
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete()->after('loan_id');
            $table->string('type')->index()->after('installment_id');
            $table->bigInteger('amount')->after('type');
            $table->bigInteger('balance_after')->default(0)->after('amount');
            $table->text('description')->nullable()->after('balance_after');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->string('action')->index()->after('user_id');
            $table->string('entity_type')->nullable()->after('action');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->json('metadata')->nullable()->after('entity_id');
            $table->string('ip_address')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'action', 'entity_type', 'entity_id', 'metadata', 'ip_address']);
        });

        Schema::table('profit_transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['loan_id']);
            $table->dropForeign(['installment_id']);
            $table->dropColumn(['user_id', 'created_by', 'loan_id', 'installment_id', 'type', 'amount', 'balance_after', 'description']);
        });

        Schema::table('profit_balances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'earned_amount', 'withdrawn_amount', 'transferred_to_modal_amount', 'available_amount']);
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['loan_id']);
            $table->dropColumn(['created_by', 'type', 'source', 'direction', 'amount', 'balance_after', 'loan_id', 'reference', 'description']);
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropMorphs('approvable');
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['request_type', 'requested_by', 'reviewed_by', 'payload', 'status', 'reviewed_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->dropForeign(['installment_id']);
            $table->dropForeign(['recorded_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['loan_id', 'installment_id', 'recorded_by', 'approved_by', 'amount', 'nominal_source', 'status', 'approved_at']);
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->dropUnique(['loan_id', 'sequence']);
            $table->dropForeign(['loan_id']);
            $table->dropColumn(['loan_id', 'sequence', 'scheduled_amount', 'paid_amount', 'outstanding_amount', 'status', 'paid_at']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['borrower_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['borrower_id', 'created_by', 'loan_code', 'principal_amount', 'administration_fee', 'net_disbursement', 'interest_amount', 'total_receivable', 'installment_count', 'status', 'approved_at', 'approved_by']);
        });

        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'borrower_code', 'full_name', 'phone', 'address', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['phone']);
            $table->dropColumn(['username', 'phone', 'role', 'must_change_password', 'last_login_at']);
        });
    }
};
