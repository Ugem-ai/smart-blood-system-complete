<?php

namespace App\Http\Middleware;

use App\Models\ChapterApiKey;
use Closure;
use Illuminate\Http\Request;

class ChapterApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $rawApiKey = trim((string) $request->header('X-API-KEY', ''));

        if ($rawApiKey === '') {
            return response()->json(['message' => 'Unauthorized. Missing API key.'], 401);
        }

        $apiKey = ChapterApiKey::query()
            ->with('chapter')
            ->where('api_key', $rawApiKey)
            ->where('is_active', true)
            ->first();

        if (! $apiKey || ! $apiKey->chapter) {
            return response()->json(['message' => 'Unauthorized. Invalid or inactive API key.'], 401);
        }

        $apiKey->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('chapter', $apiKey->chapter);
        $request->attributes->set('chapter_api_key', $apiKey);

        return $next($request);
    }
}
