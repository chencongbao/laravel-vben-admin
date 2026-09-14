<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropAfterBackfill(
            config('laravel-vben-admin.tables.login_logs', 'admin_login_logs'),
            'login',
        );
        $this->dropAfterBackfill(
            config('laravel-vben-admin.tables.audit_logs', 'admin_audit_logs'),
            'operation',
        );
    }

    public function down(): void
    {
        $loginTable = config('laravel-vben-admin.tables.login_logs', 'admin_login_logs');
        if (! Schema::hasTable($loginTable)) {
            Schema::create($loginTable, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('username', 120);
                $table->boolean('succeeded')->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('failure_code', 80)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        $auditTable = config('laravel-vben-admin.tables.audit_logs', 'admin_audit_logs');
        if (! Schema::hasTable($auditTable)) {
            Schema::create($auditTable, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_id')->nullable()->index();
                $table->string('action', 160)->index();
                $table->string('subject_type', 160)->nullable();
                $table->string('subject_id', 120)->nullable();
                $table->json('changes')->nullable();
                $table->json('context')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    private function dropAfterBackfill(string $legacyTable, string $logType): void
    {
        if (! Schema::hasTable($legacyTable)) {
            return;
        }

        $legacyCount = DB::table($legacyTable)->count();
        $backfilledCount = DB::table('activity_log')
            ->where('log_type', $logType)
            ->where('legacy_source', $legacyTable)
            ->count();

        if ($backfilledCount < $legacyCount) {
            throw new RuntimeException(
                "Refusing to drop [{$legacyTable}]: {$legacyCount} legacy rows exist, but only {$backfilledCount} were backfilled.",
            );
        }

        Schema::drop($legacyTable);
    }
};
