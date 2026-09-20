<?php

namespace Chencongbao\LaravelVbenAdmin\Services;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\Contracts\Activity;

final class ActivityAuditRecorder implements AuditRecorder
{
    use RedactsActivityData;

    public function __construct(
        private readonly Request $request,
        private readonly AuditLogRegistry $registry,
    ) {}

    public function record(AdminUser $actor, string $action, ?object $subject = null, array $changes = [], array $context = []): void
    {
        $definition = $this->registry->action($action);
        $logger = activity(config('laravel-vben-admin.activity_log.log_name', 'admin'))
            ->causedBy($actor)
            ->event($action)
            ->withChanges($this->redactActivityData($changes))
            ->withProperties([
                'context' => $this->redactActivityData($context),
                'request_input' => $this->redactActivityData(
                    $this->request->only($this->registry->requestFields($action)),
                ),
                'request_id' => $this->request->header('X-Request-ID'),
                'route_name' => $this->request->route()?->getName(),
                'action_label_key' => $definition['label_key'],
                'action_module' => $definition['module'],
                'action_type' => $definition['type'],
            ])
            ->tap(function (Activity $activity): void {
                $activity->log_type = 'operation';
                $activity->ip_address = $this->request->ip();
                $activity->method = strtoupper($this->request->method());
                $activity->path = '/'.ltrim($this->request->path(), '/');
                $activity->user_agent = mb_substr((string) $this->request->userAgent(), 0, 2000);
            });

        if ($subject instanceof Model) {
            $logger->performedOn($subject);
        }

        $logger->log($action);
    }
}
