<?php

namespace MohamedAhmed01\HttpQueryPolyfill;

use Illuminate\Http\Client\PendingRequest;

class HttpQueryMacro
{
    /**
     * Register the query() macro on PendingRequest.
     *
     * Mirrors Laravel 13.19's native implementation:
     * https://github.com/laravel/framework/pull/60663
     */
    public static function register(): void
    {
        if (PendingRequest::hasMacro('query')) {
            return;
        }

        PendingRequest::macro('query', static::closure());
    }

    /**
     * The macro closure matching PendingRequest::query().
     *
     * @return \Closure(string, array|\JsonSerializable|\Illuminate\Contracts\Support\Arrayable=): (\Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface)
     */
    public static function closure(): \Closure
    {
        return function (string $url, $data = []) {
            /** @var PendingRequest $this */
            return $this->send('QUERY', $url, [
                $this->bodyFormat => $data,
            ]);
        };
    }
}
