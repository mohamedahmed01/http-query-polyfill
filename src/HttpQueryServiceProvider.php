<?php

namespace MohamedAhmed01\HttpQueryPolyfill;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class HttpQueryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the HTTP QUERY polyfill.
     *
     * Registers PendingRequest::query() only when the framework does not
     * already provide it (Laravel < 13.19.0). Safe to keep installed on
     * newer Laravel versions — registration is a no-op there.
     */
    public function boot(): void
    {
        if ($this->hasNativeQueryMethod()) {
            return;
        }

        HttpQueryMacro::register();
    }

    /**
     * Determine whether Laravel already ships PendingRequest::query().
     */
    protected function hasNativeQueryMethod(): bool
    {
        return (new ReflectionClass(PendingRequest::class))->hasMethod('query');
    }
}
