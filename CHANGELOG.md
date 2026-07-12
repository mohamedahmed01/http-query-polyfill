# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-13

Initial release of the Laravel `Http::query()` polyfill (RFC 10008).

### Added

- `Http::query()` / `PendingRequest::query()` matching Laravel 13.19+ behavior — sends an HTTP `QUERY` request with data in the body (JSON by default; respects `asForm()` and other body format helpers)
- Automatic no-op on Laravel 13.19+: native `PendingRequest::query()` is used when present; the polyfill macro is not registered
- `MakesHttpQueryRequests` testing trait with `query()` and `queryJson()` helpers for Laravel &lt; 13.19
- Package auto-discovery for `HttpQueryServiceProvider`
- CI test matrix across PHP 8.1–8.4 and Laravel 10–13

### Compatibility

| Laravel | Behavior |
|---------|----------|
| 10.x – 13.18 | Macro polyfill registered |
| 13.19+ | No-op; native `Http::query()` used |

### Requirements

- PHP 8.1+
- `illuminate/http` and `illuminate/support` ^10 \| ^11 \| ^12 \| ^13
- `guzzlehttp/guzzle` ^7.8 (required to use the Laravel HTTP client)

[1.0.0]: https://github.com/mohamedahmed01/http-query-polyfill/releases/tag/v1.0.0
