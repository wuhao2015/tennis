<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class AddRequestTraceContext
{
    private const string TRACE_HEADER = 'X-Amzn-Trace-Id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header(self::TRACE_HEADER);

        if (filled($traceId)) {
            Context::add(self::TRACE_HEADER, $traceId);
        } else {
            Context::forget(self::TRACE_HEADER);
        }

        return $next($request);
    }
}
