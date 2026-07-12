<?php

namespace MohamedAhmed01\HttpQueryPolyfill\Tests;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MohamedAhmed01\HttpQueryPolyfill\HttpQueryMacro;
use MohamedAhmed01\HttpQueryPolyfill\HttpQueryServiceProvider;
use Orchestra\Testbench\TestCase;
use ReflectionClass;

class HttpQueryTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [HttpQueryServiceProvider::class];
    }

    public function test_query_sends_json_body_by_default(): void
    {
        Http::fake();

        Http::query('http://foo.com/search', [
            'filter' => 'active',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://foo.com/search' &&
                $request->method() === 'QUERY' &&
                $request->hasHeader('Content-Type', 'application/json') &&
                $request['filter'] === 'active';
        });
    }

    public function test_query_sends_form_data_when_as_form(): void
    {
        Http::fake();

        Http::asForm()->query('http://foo.com/search', [
            'filter' => 'active',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://foo.com/search' &&
                $request->method() === 'QUERY' &&
                $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded') &&
                $request['filter'] === 'active';
        });
    }

    public function test_nested_filter_payload(): void
    {
        Http::fake();

        Http::query('https://api.example.com/search', [
            'filter' => ['status' => 'active'],
        ]);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'QUERY' &&
                $request['filter'] === ['status' => 'active'];
        });
    }

    public function test_polyfill_macro_implementation(): void
    {
        PendingRequest::macro('queryViaPolyfill', HttpQueryMacro::closure());

        Http::fake();

        Http::queryViaPolyfill('http://foo.com/search', [
            'filter' => 'active',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://foo.com/search' &&
                $request->method() === 'QUERY' &&
                $request->hasHeader('Content-Type', 'application/json') &&
                $request['filter'] === 'active';
        });
    }

    public function test_provider_is_noop_when_native_query_exists(): void
    {
        $hasNative = (new ReflectionClass(PendingRequest::class))->hasMethod('query');

        if (! $hasNative) {
            $this->markTestSkipped('Native PendingRequest::query() is not available on this Laravel version.');
        }

        $this->assertFalse(
            PendingRequest::hasMacro('query'),
            'Polyfill must not register a macro when Laravel already provides query().'
        );
    }
}
