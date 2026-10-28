<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('platform')->create('platform_cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database_name')->unique();
            $table->text('connection_config')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('platform')->create('platform_account_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained('platform_cooperatives')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_user_id');
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('borrower_code')->nullable();
            $table->timestamps();
            $table->unique(['cooperative_id', 'tenant_user_id'], 'pal_link_uniq');
            $table->index(['username', 'cooperative_id'], 'pal_user_idx');
            $table->index(['phone', 'cooperative_id'], 'pal_phone_idx');
            $table->index(['borrower_code', 'cooperative_id'], 'pal_code_idx');
        });

        Schema::connection('platform')->create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('platform')->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::connection('platform')->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::connection('platform')->create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::connection('platform')->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::connection('platform')->create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::connection('platform')->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('platform_account_links');
        Schema::connection('platform')->dropIfExists('platform_admins');
        Schema::connection('platform')->dropIfExists('platform_cooperatives');
        Schema::connection('platform')->dropIfExists('sessions');
        Schema::connection('platform')->dropIfExists('cache');
        Schema::connection('platform')->dropIfExists('cache_locks');
        Schema::connection('platform')->dropIfExists('jobs');
        Schema::connection('platform')->dropIfExists('job_batches');
        Schema::connection('platform')->dropIfExists('failed_jobs');
    }
};
