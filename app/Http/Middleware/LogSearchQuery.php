<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SearchLog;

class LogSearchQuery
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $query = $request->input('q');

        if ($query && strlen($query) >= 2) {
            SearchLog::create([
                'query' => strtolower($query),
                'user_id' => $request->user()?->id,
            ]);
        }
    }
}
