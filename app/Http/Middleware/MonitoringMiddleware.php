<?php

namespace App\Http\Middleware;

use App\Services\MonitoringMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitoringMiddleware
{
    public function __construct(private readonly MonitoringMetricsService $metrics)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $path = (string) ($request->route()?->uri() ?? $request->path());

        $this->metrics->recordApiResponse(
            method: $request->method(),
            path: $path,
            status: $response->getStatusCode(),
            durationMs: $durationMs
        );

        return $response;
    }
}
