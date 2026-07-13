# Laravel HTTP Query Polyfill

Backward-compatible polyfill for [`Http::query()`](https://github.com/laravel/framework/pull/60663) introduced in Laravel 13.19.0 (RFC 10008).

On Laravel **&lt; 13.19**, this package registers `PendingRequest::query()` so you can send HTTP `QUERY` requests with a body. On Laravel **13.19+**, registration is skipped and the native method is used unchanged.

## Installation

```bash
composer require mohamedahmed01/laravel-http-query-polyfill
```

The service provider is auto-discovered. No further setup is required.

## Usage

Same API as Laravel 13.19+:

```php
use Illuminate\Support\Facades\Http;

$response = Http::query('https://api.example.com/search', [
    'filter' => ['status' => 'active'],
]);
```

JSON is the default body format. Use existing fluent helpers for other formats:

```php
Http::asForm()->query('https://api.example.com/search', [
    'filter' => 'active',
]);
```

URL query strings are unchanged — keep using `withQueryParameters()` or `Http::get($url, $query)` for those.

## Testing helpers (Laravel &lt; 13.19)

Laravel 13.19 also added `$this->query()` / `$this->queryJson()` on HTTP tests. Those live on a trait, so they cannot be auto-registered. On older Laravel, add the polyfill trait to your base test case:

```php
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use MohamedAhmed01\HttpQueryPolyfill\Testing\MakesHttpQueryRequests;

abstract class TestCase extends BaseTestCase
{
    use MakesHttpQueryRequests;
}
```

Then:

```php
$this->queryJson('/search', ['filter' => 'active'])->assertOk();
```

Do **not** use this trait on Laravel 13.19+ — the methods already exist and will collide.

## Compatibility

| Laravel | Behavior |
|---------|----------|
| 10.x – 13.18 | Macro polyfill registered |
| 13.19+ | No-op; native `Http::query()` used |

## Live integration (optional)

Against [Ayder](https://github.com/A1darbek/ayder)'s `/broker/query` QUERY endpoint:

```bash
# from an Ayder checkout
docker compose up -d --build
curl -fsS http://127.0.0.1:1109/health

# from this package
vendor/bin/phpunit --filter AyderHttpQueryIntegrationTest
```

Coverage includes:
- `Http::query()` with a JSON body against a real QUERY server
- `Accept-Query`, `ETag`, `Content-Location`
- conditional revalidation with `If-None-Match` → `304`

Tests skip automatically when Ayder is not reachable. Override with `AYDER_BASE_URL` / `AYDER_TOKEN`.

## License

MIT
