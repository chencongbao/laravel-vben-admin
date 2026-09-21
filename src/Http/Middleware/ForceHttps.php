<?php

namespace Chencongbao\LaravelVbenAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
