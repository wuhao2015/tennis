<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RequestTraceLoggingTest extends TestCase
{
    public function test_request_logs_include_only_the_current_requests_trace_header(): void
    {
        $logs = new TestHandler(bubble: false);
        Log::channel()->pushHandler($logs);
        Route::get('/trace-test', function (): Response {
            Log::info('Handling request');

            return response()->noContent();
        });

        $traceId = 'Root=1-67891233-abcdef012345678912345678;Sampled=1';
        $this->get('/trace-test', ['X-Amzn-Trace-Id' => $traceId])->assertNoContent();
        $this->get('/trace-test')->assertNoContent();

        [$traced, $untraced] = $logs->getRecords();
        $this->assertSame($traceId, $traced->extra['X-Amzn-Trace-Id'] ?? null);
        $this->assertArrayNotHasKey('X-Amzn-Trace-Id', $untraced->extra);
    }

    public function test_reported_exceptions_include_the_request_trace_header(): void
    {
        $logs = new TestHandler(bubble: false);
        Log::channel()->pushHandler($logs);
        Route::get('/trace-error', function (): never {
            throw new RuntimeException('Trace test exception');
        });

        $traceId = 'Root=1-67891233-abcdef012345678912345678';
        $this->getJson('/trace-error', ['X-Amzn-Trace-Id' => $traceId])->assertInternalServerError();

        $record = $logs->getRecords()[0];
        $this->assertSame('Trace test exception', $record->message);
        $this->assertSame($traceId, $record->extra['X-Amzn-Trace-Id'] ?? null);
    }
}
