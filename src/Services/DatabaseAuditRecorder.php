<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminAuditLog;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class DatabaseAuditRecorder implements AuditRecorder
{
    public function __construct(private readonly Request $request) {}

    public function record(AdminUser $actor, string $action, ?object $subject = null, array $changes = [], array $context = []): void
    {
        AdminAuditLog::query()->create([
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : ($subject ? $subject::class : null),
            'subject_id' => $subject instanceof Model ? (string) $subject->getKey() : null,
            'changes' => $this->redact($changes),
            'context' => $this->redact($context),
            'ip_address' => $this->request->ip(),
        ]);
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|credential|authorization/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
