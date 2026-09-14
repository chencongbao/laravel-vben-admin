<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Middleware;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AdminUser || ! $user->roles()->where('is_active', true)->where('is_super_admin', true)->exists()) {
            return new JsonResponse(['message' => 'Forbidden.', 'code' => 'ADMIN_SUPER_ADMIN_REQUIRED'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
