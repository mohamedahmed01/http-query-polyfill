<?php

namespace MohamedAhmed01\HttpQueryPolyfill\Testing;

/**
 * Testing helpers that mirror Laravel 13.19+'s query() / queryJson() methods.
 *
 * Use this trait on your TestCase when running Laravel < 13.19:
 *
 *     class TestCase extends BaseTestCase
 *     {
 *         use CreatesApplication;
 *         use MakesHttpQueryRequests;
 *     }
 *
 * Do not use this trait on Laravel 13.19+ — those methods already exist on
 * MakesHttpRequests and combining both will cause a method collision.
 */
trait MakesHttpQueryRequests
{
    /**
     * Visit the given URI with a QUERY request.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \Illuminate\Testing\TestResponse
     */
    public function query($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('QUERY', $uri, $data, $cookies, [], $server);
    }

    /**
     * Visit the given URI with a QUERY request, expecting a JSON response.
     *
     * @param  \Illuminate\Support\Uri|string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @param  int  $options
     * @return \Illuminate\Testing\TestResponse
     */
    public function queryJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return $this->json('QUERY', $uri, $data, $headers, $options);
    }
}
