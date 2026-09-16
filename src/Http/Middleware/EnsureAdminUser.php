<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Middleware;

use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof AdminUser || ! $user->is_active || ! $token?->can('admin')) {
            $token?->delete();

            return new JsonResponse([
                'message' => 'Unauthenticated.',
                'code' => 'ADMIN_AUTH_REQUIRED',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
