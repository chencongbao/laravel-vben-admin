<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Controllers;

use Chencongbao\LaravelVbenAdmin\Contracts\AuditRecorder;
use Chencongbao\LaravelVbenAdmin\Models\AdminLoginIpBlock;
use Chencongbao\LaravelVbenAdmin\Models\AdminSecurityEvent;
use Chencongbao\LaravelVbenAdmin\Services\LoginIpWhitelist;
use Chencongbao\LaravelVbenAdmin\Support\AdminPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminSecurityController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'code' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'status' => ['nullable', Rule::in(['open', 'resolved'])],
            'username' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(AdminSecurityEvent::query()
            ->when($data['id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($data['code'] ?? null, fn ($query, $code) => $query->where('code', $code))
            ->when($data['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($data['status'] ?? null, fn ($query, $status) => $status === 'open' ? $query->whereNull('resolved_at') : $query->whereNotNull('resolved_at'))
            ->when($data['username'] ?? null, fn ($query, $username) => $query->where('username', 'like', "%{$username}%"))
            ->when($data['ip_address'] ?? null, fn ($query, $ip) => $query->where('ip_address', $ip))
            ->latest('id')
            ->paginate(AdminPagination::perPage($data['per_page'] ?? null)));
    }

    public function resolve(Request $request, AdminSecurityEvent $securityEvent): JsonResponse
    {
        if ($securityEvent->resolved_at !== null) {
            return response()->json(['event' => $securityEvent]);
        }

        DB::transaction(function () use ($request, $securityEvent): void {
            $before = $securityEvent->only(['resolved_at', 'resolved_by']);
            $securityEvent->forceFill(['resolved_at' => now(), 'resolved_by' => $request->user()->getKey()])->save();
            $this->audit->record($request->user(), 'system.security.event.resolved', $securityEvent, [
                'before' => $before,
                'after' => $securityEvent->only(['resolved_at', 'resolved_by']),
            ]);
        });

        return response()->json(['event' => $securityEvent->fresh()]);
    }

    public function blocks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'ip_address' => ['nullable', 'ip'],
            'status' => ['nullable', Rule::in(['active', 'released', 'expired'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(AdminLoginIpBlock::query()
            ->when($data['id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($data['ip_address'] ?? null, fn ($query, $ip) => $query->where('ip_address', $ip))
            ->when($data['status'] ?? null, function ($query, $status) {
                return match ($status) {
                    'active' => $query->whereNull('released_at')->where(fn ($nested) => $nested->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                    'released' => $query->whereNotNull('released_at'),
                    'expired' => $query->whereNull('released_at')->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                };
            })
            ->latest('id')
            ->paginate(AdminPagination::perPage($data['per_page'] ?? null)));
    }

    public function block(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ip_address' => ['required', 'ip', 'max:45'],
            'reason_code' => ['required', 'string', 'max:120', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);
        if (! LoginIpWhitelist::isValidRule($data['ip_address']) || str_contains($data['ip_address'], '/')) {
            return response()->json(['message' => 'Only an exact IP address can be blocked.', 'code' => 'SECURITY_BLOCK_IP_INVALID'], 422);
        }
        if (AdminLoginIpBlock::query()->where('ip_address', $data['ip_address'])->whereNull('released_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
            return response()->json(['message' => 'This IP address is already blocked.', 'code' => 'SECURITY_IP_ALREADY_BLOCKED'], 422);
        }

        $block = DB::transaction(function () use ($request, $data): AdminLoginIpBlock {
            $block = AdminLoginIpBlock::query()->create([
                'ip_address' => $data['ip_address'],
                'reason_code' => $data['reason_code'],
                'is_automatic' => false,
                'expires_at' => isset($data['duration_hours']) ? now()->addHours($data['duration_hours']) : null,
                'created_by' => $request->user()->getKey(),
            ]);
            $this->audit->record($request->user(), 'system.security.ip-block.created', $block, ['after' => $block->toArray()]);

            return $block;
        });

        return response()->json(['block' => $block], 201);
    }

    public function release(Request $request, AdminLoginIpBlock $ipBlock): JsonResponse
    {
        if ($ipBlock->released_at !== null) {
            return response()->json(['block' => $ipBlock]);
        }

        DB::transaction(function () use ($request, $ipBlock): void {
            $before = $ipBlock->only(['released_at', 'released_by']);
            $ipBlock->forceFill(['released_at' => now(), 'released_by' => $request->user()->getKey()])->save();
            $this->audit->record($request->user(), 'system.security.ip-block.released', $ipBlock, [
                'before' => $before,
                'after' => $ipBlock->only(['released_at', 'released_by']),
            ]);
        });

        return response()->json(['block' => $ipBlock->fresh()]);
    }
}
