<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core system tables: approvals, login history, OTP codes, settings,
 * integrations, audit logs, saved filters and configurable custom fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('successful')->default(true);
            $table->string('reason')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10); // email, sms
            $table->string('purpose', 30); // phone_verification, two_factor, login
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->boolean('via_database')->default(true);
            $table->boolean('via_mail')->default(true);
            $table->boolean('via_sms')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'event']);
        });

        Schema::create('api_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index(); // vin_decoder, payment_gateway, email, sms, other
            $table->string('name');
            $table->string('provider', 50);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->json('config')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 60)->index();
            $table->string('action', 100);
            $table->string('endpoint', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('successful')->default(false);
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60)->index();
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->string('name', 100);
            $table->json('filters');
            $table->timestamps();
            $table->index(['user_id', 'module']);
        });

        // Concurrency-safe document numbering (invoices, jobs, POs, stock numbers ...).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50)->default('vehicles')->index();
            $table->string('key', 60);
            $table->string('label', 100);
            $table->string('type', 20)->default('text'); // text, number, date, select, checkbox, textarea
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['module', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['custom_fields', 'number_sequences', 'saved_filters', 'audit_logs', 'integration_logs', 'api_integrations',
            'notification_settings', 'settings', 'otp_codes', 'login_histories', 'user_approval_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
