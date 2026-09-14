<?php

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Chencongbao\LaravelVbenAdmin\Services\LoginClientClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function (Blueprint $table): void {
                $table->id();
                $table->string('log_name')->nullable()->index();
                $table->string('log_type', 20)->default('operation');
                $table->text('description');
                $table->nullableMorphs('subject', 'subject');
                $table->string('event')->nullable();
                $table->nullableMorphs('causer', 'causer');
                $table->json('attribute_changes')->nullable();
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('method', 12)->nullable();
                $table->text('path')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('legacy_source')->nullable();
                $table->unsignedBigInteger('legacy_id')->nullable();
                $table->timestamps();
                $table->index(['log_name', 'log_type', 'created_at'], 'activity_log_name_type_time_index');
                $table->unique(['legacy_source', 'legacy_id'], 'activity_log_legacy_unique');
            });
        } else {
            $this->upgradeExistingActivityTable();
        }

        $this->backfillLoginLogs();
        $this->backfillAuditLogs();
    }

    public function down(): void
    {
        // activity_log may be shared with the host application. Keep this
        // append-only audit data on rollback instead of destructively dropping it.
    }

    private function upgradeExistingActivityTable(): void
    {
        $needsLegacyIndex = ! Schema::hasColumn('activity_log', 'legacy_source')
            || ! Schema::hasColumn('activity_log', 'legacy_id');
        $columns = [
            'attribute_changes' => fn (Blueprint $table) => $table->json('attribute_changes')->nullable(),
            'log_type' => fn (Blueprint $table) => $table->string('log_type', 20)->default('operation'),
            'ip_address' => fn (Blueprint $table) => $table->string('ip_address', 45)->nullable(),
            'method' => fn (Blueprint $table) => $table->string('method', 12)->nullable(),
            'path' => fn (Blueprint $table) => $table->text('path')->nullable(),
            'user_agent' => fn (Blueprint $table) => $table->text('user_agent')->nullable(),
            'legacy_source' => fn (Blueprint $table) => $table->string('legacy_source')->nullable(),
            'legacy_id' => fn (Blueprint $table) => $table->unsignedBigInteger('legacy_id')->nullable(),
        ];

        foreach ($columns as $column => $addColumn) {
            if (! Schema::hasColumn('activity_log', $column)) {
                Schema::table('activity_log', $addColumn);
            }
        }

        if ($needsLegacyIndex) {
            Schema::table('activity_log', function (Blueprint $table): void {
                $table->unique(['legacy_source', 'legacy_id'], 'activity_log_legacy_unique');
            });
        }
    }

    private function backfillLoginLogs(): void
    {
        $table = config('laravel-vben-admin.tables.login_logs', 'admin_login_logs');
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'log_name' => config('laravel-vben-admin.activity_log.log_name', 'admin'),
                    'log_type' => 'login',
                    'description' => $row->succeeded ? '登录成功' : '登录失败',
                    'subject_type' => null,
                    'subject_id' => null,
                    'event' => $row->succeeded ? 'auth.login.succeeded' : 'auth.login.failed',
                    'causer_type' => $row->user_id ? AdminUser::class : null,
                    'causer_id' => $row->user_id,
                    'attribute_changes' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'properties' => json_encode([
                        'username' => $row->username,
                        'succeeded' => (bool) $row->succeeded,
                        'failure_code' => $row->failure_code,
                        'client_type' => app(LoginClientClassifier::class)->classify($row->user_agent),
                        'legacy' => ['table' => $table, 'id' => $row->id],
                    ], JSON_UNESCAPED_UNICODE),
                    'ip_address' => $row->ip_address,
                    'method' => 'POST',
                    'path' => '/api/admin/auth/login',
                    'user_agent' => $row->user_agent,
                    'legacy_source' => $table,
                    'legacy_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ];
            }

            if ($records !== []) {
                DB::table('activity_log')->insertOrIgnore($records);
            }
        });
    }

    private function backfillAuditLogs(): void
    {
        $table = config('laravel-vben-admin.tables.audit_logs', 'admin_audit_logs');
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
            $records = [];
            foreach ($rows as $row) {
                $context = $this->decodeJson($row->context);
                $context['legacy'] = ['table' => $table, 'id' => $row->id];
                $records[] = [
                    'log_name' => config('laravel-vben-admin.activity_log.log_name', 'admin'),
                    'log_type' => 'operation',
                    'description' => $row->action,
                    'subject_type' => $row->subject_type,
                    'subject_id' => $row->subject_id,
                    'event' => $row->action,
                    'causer_type' => AdminUser::class,
                    'causer_id' => $row->actor_id,
                    'attribute_changes' => json_encode($this->decodeJson($row->changes), JSON_UNESCAPED_UNICODE),
                    'properties' => json_encode(['context' => $context], JSON_UNESCAPED_UNICODE),
                    'ip_address' => $row->ip_address,
                    'method' => null,
                    'path' => null,
                    'user_agent' => null,
                    'legacy_source' => $table,
                    'legacy_id' => $row->id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ];
            }

            if ($records !== []) {
                DB::table('activity_log')->insertOrIgnore($records);
            }
        });
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
