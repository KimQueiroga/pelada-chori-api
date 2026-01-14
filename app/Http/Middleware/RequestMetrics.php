<?php

namespace App\Http\Middleware;

use App\Services\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('metrics') || $request->is('api/metrics')) {
            return $next($request);
        }

        $start = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $durationMs = (microtime(true) - $start) * 1000;

        $route = $request->route();
        $routeLabel = $route?->uri() ?? $request->path();

        $method = $request->method();
        $status = $response->getStatusCode();

        MetricsService::increment('http_requests_total', [
            'method' => $method,
            'route' => $routeLabel,
            'status' => $status,
        ]);

        MetricsService::observeHistogram('http_request_duration_ms', [
            'method' => $method,
            'route' => $routeLabel,
        ], $durationMs);

        return $response;
    }
}
