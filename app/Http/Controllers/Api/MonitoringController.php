<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MonitoringMetricsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitoringController extends Controller
{
    public function metrics(Request $request, MonitoringMetricsService $metrics): Response
    {
        $token = (string) config('services.monitoring.metrics_token', '');

        if ($token !== '') {
            $provided = (string) $request->header('X-Metrics-Token', '');
            if (! hash_equals($token, $provided)) {
                return response('Unauthorized', 401, ['Content-Type' => 'text/plain']);
            }
        }

        return response($metrics->prometheusPayload(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    public function health(MonitoringMetricsService $metrics): Response
    {
        $snapshot = $metrics->healthSnapshot();
        $code = $snapshot['status'] === 'ok' ? 200 : 503;

        return response()->json($snapshot, $code);
    }
}
