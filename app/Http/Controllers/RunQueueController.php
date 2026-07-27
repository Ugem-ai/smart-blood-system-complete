<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RunQueueController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->header('X-RUN-QUEUE-TOKEN');
        $expectedToken = env('QUEUE_TRIGGER_TOKEN');

        if (! $token || ! $expectedToken || ! hash_equals($expectedToken, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $jobsBefore = DB::table('jobs')->count();
        $failedBefore = DB::table('failed_jobs')->count();

        $exitCode = Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        $output = Artisan::output();
        $jobsAfter = DB::table('jobs')->count();
        $failedAfter = DB::table('failed_jobs')->count();

        return response()->json([
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'jobs_before' => $jobsBefore,
            'jobs_after' => $jobsAfter,
            'jobs_processed' => max(0, $jobsBefore - $jobsAfter),
            'failed_jobs_before' => $failedBefore,
            'failed_jobs_after' => $failedAfter,
            'failed_jobs_new' => max(0, $failedAfter - $failedBefore),
            'output' => trim($output),
        ], $exitCode === 0 ? 200 : 500);
    }
}
