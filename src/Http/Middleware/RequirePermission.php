<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Middleware;

use Chencongbao\LaravelVbenAdmin\Contracts\Authorizer;
use Chencongbao\LaravelVbenAdmin\Models\AdminUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function __construct(private readonly Authorizer $authorizer) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof AdminUser || ! $this->authorizer->allows($user, $permission)) {
            return new JsonResponse(['message' => 'Forbidden.', 'code' => 'ADMIN_PERMISSION_DENIED'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
