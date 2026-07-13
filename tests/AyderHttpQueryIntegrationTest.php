<?php

namespace MohamedAhmed01\HttpQueryPolyfill\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use MohamedAhmed01\HttpQueryPolyfill\HttpQueryServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Live integration against Ayder's RFC 10008 QUERY endpoint.
 *
 * @see https://github.com/A1darbek/ayder
 * @see https://github.com/A1darbek/ayder/tree/main/demos/http_query_method
 * @see https://github.com/A1darbek/ayder/tree/main/demos/http_query_recovery_snapshot
 *
 * Start Ayder locally first:
 *
 *     docker compose up -d --build
 *     curl -fsS http://127.0.0.1:1109/health
 *
 * Optional env:
 *   AYDER_BASE_URL  (default http://127.0.0.1:1109)
 *   AYDER_TOKEN     (default dev)
 */
class AyderHttpQueryIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [HttpQueryServiceProvider::class];
    }

    public function test_http_query_filters_ayder_broker_events(): void
    {
        [$base, $token] = $this->ayderConnectionOrSkip();

        $runId = 'php_query_'.Str::lower(Str::random(8));
        $topic = 'http_query_'.$runId;
        $group = 'http_query_reader_'.$runId;

        $client = Http::baseUrl($base)
            ->withToken($token)
            ->acceptJson()
            ->timeout(10);

        $createTopic = $client->post('/broker/topics', [
            'name' => $topic,
            'partitions' => 1,
        ]);

        $this->assertTrue(
            $createTopic->successful(),
            'Failed to create Ayder topic: '.$createTopic->body()
        );

        $events = implode("\n", [
            json_encode([
                'event_id' => 'evt-query-001',
                'kind' => 'payment',
                'status' => 'SUCCESS',
                'merchant' => 'design_partner',
                'amount' => 42,
                'currency' => 'USD',
            ]),
            json_encode([
                'event_id' => 'evt-query-002',
                'kind' => 'payment',
                'status' => 'PENDING_REVIEW',
                'merchant' => 'design_partner',
                'amount' => 99,
                'currency' => 'USD',
            ]),
            json_encode([
                'event_id' => 'evt-query-003',
                'kind' => 'payment',
                'status' => 'SUCCESS',
                'merchant' => 'design_partner',
                'amount' => 17,
                'currency' => 'USD',
            ]),
        ])."\n";

        $produce = $client
            ->withBody($events, 'application/x-ndjson')
            ->post("/broker/topics/{$topic}/produce-ndjson?partition=0&timeout_ms=5000");

        $this->assertTrue(
            $produce->successful(),
            'Failed to produce NDJSON events: '.$produce->body()
        );

        $queryPayload = [
            'source' => [
                'topic' => $topic,
                'partition' => 0,
                'from_offset' => 0,
                'to_offset' => 3,
                'sealed_only' => true,
                'limit' => 10,
            ],
            'include' => [
                'commit_status_for_group' => $group,
            ],
            'filter' => [
                [
                    'field' => 'status',
                    'op' => 'eq',
                    'value' => 'SUCCESS',
                ],
            ],
            'aggregate' => [
                'group_by' => ['status'],
            ],
            'transform' => [
                'left_fields' => ['event_id', 'status', 'amount', 'currency'],
                'emit_rows' => 5,
            ],
        ];

        $response = $this->ayderQueryClient($base, $token)->query('/broker/query', $queryPayload);

        $this->assertSame(
            200,
            $response->status(),
            'Expected QUERY HTTP 200, got '.$response->status().': '.$response->body()
        );

        $this->assertTrue(
            $response->hasHeader('Accept-Query'),
            'Expected Accept-Query response header'
        );

        $this->assertStringContainsString(
            'application/json',
            strtolower($response->header('Accept-Query') ?? ''),
            'Expected Accept-Query: application/json'
        );

        $json = $response->json();

        $this->assertTrue($json['ok'] ?? false, 'Expected ok=true in QUERY response: '.$response->body());
        $this->assertCount(2, $json['rows'] ?? [], 'Expected 2 SUCCESS rows');
        $this->assertCount(1, $json['aggregates'] ?? [], 'Expected 1 aggregate group');
        $this->assertSame(3, $json['source']['consumed'] ?? null, 'Expected source.consumed=3');

        $statuses = collect($json['rows'])->pluck('status')->all();
        $this->assertSame(['SUCCESS', 'SUCCESS'], $statuses);
    }

    public function test_http_query_returns_etag_content_location_and_304_revalidation(): void
    {
        [$base, $token] = $this->ayderConnectionOrSkip();

        $runId = 'php_query_snap_'.Str::lower(Str::random(8));
        $topic = 'payment_events_'.$runId;
        $group = 'payment_worker_'.$runId;

        $admin = Http::baseUrl($base)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(10);

        $createTopic = $admin->post('/broker/topics', [
            'name' => $topic,
            'partitions' => 1,
        ]);

        $this->assertTrue(
            $createTopic->successful(),
            'Failed to create Ayder topic: '.$createTopic->body()
        );

        $events = implode("\n", [
            json_encode([
                'event_id' => 'pay_evt_001',
                'payment_id' => 'pay_001',
                'provider_order_id' => 'po_001',
                'provider_commitment' => 'UNKNOWN',
                'safe_to_close' => false,
                'manual_reconciliation_required' => true,
            ]),
            json_encode([
                'event_id' => 'pay_evt_002',
                'payment_id' => 'pay_002',
                'provider_order_id' => 'po_002',
                'provider_commitment' => 'SUCCESS',
                'safe_to_close' => true,
                'manual_reconciliation_required' => false,
            ]),
            json_encode([
                'event_id' => 'pay_evt_003',
                'payment_id' => 'pay_003',
                'provider_order_id' => 'po_003',
                'provider_commitment' => 'UNKNOWN',
                'safe_to_close' => false,
                'manual_reconciliation_required' => true,
            ]),
        ])."\n";

        $produce = Http::baseUrl($base)
            ->withToken($token)
            ->timeout(10)
            ->withBody($events, 'application/x-ndjson')
            ->post("/broker/topics/{$topic}/produce-ndjson?partition=0&timeout_ms=5000&idempotency_key={$runId}_initial");

        $this->assertTrue(
            $produce->successful(),
            'Failed to produce NDJSON events: '.$produce->body()
        );

        $commit = $admin->post('/broker/commit', [
            'topic' => $topic,
            'group' => $group,
            'partition' => 0,
            'offset' => 0,
        ]);

        $this->assertTrue(
            $commit->successful(),
            'Failed to commit baseline offset: '.$commit->body()
        );

        $queryPayload = [
            'source' => [
                'topic' => $topic,
                'partition' => 0,
                'from_offset' => 0,
                'to_offset' => 3,
                'limit' => 10,
                'sealed_only' => true,
            ],
            'filter' => [
                [
                    'field' => 'provider_commitment',
                    'op' => 'eq',
                    'value' => 'UNKNOWN',
                ],
                [
                    'field' => 'safe_to_close',
                    'op' => 'eq',
                    'value' => false,
                ],
            ],
            'include' => [
                'metadata' => true,
                'commit_status_for_group' => $group,
            ],
            'transform' => [
                'left_fields' => [
                    'event_id',
                    'payment_id',
                    'provider_order_id',
                    'provider_commitment',
                    'safe_to_close',
                    'manual_reconciliation_required',
                ],
            ],
            'explain' => true,
        ];

        // Laravel polyfill client → QUERY with JSON body → Ayder
        $response = $this->ayderQueryClient($base, $token)->query('/broker/query', $queryPayload);

        $this->assertSame(
            200,
            $response->status(),
            'Expected QUERY HTTP 200, got '.$response->status().': '.$response->body()
        );

        $acceptQuery = $response->header('Accept-Query');
        $etag = $response->header('ETag');
        $contentLocation = $response->header('Content-Location');

        $this->assertSame(
            'application/json',
            $acceptQuery,
            'Expected Accept-Query: application/json, got: '.var_export($acceptQuery, true)
        );

        $this->assertNotEmpty($etag, 'Expected ETag response header');
        $this->assertMatchesRegularExpression(
            '/^"ayder-query-sha256-.+"$/',
            $etag,
            'Expected Ayder query ETag, got: '.$etag
        );

        $this->assertNotEmpty($contentLocation, 'Expected Content-Location response header');
        $this->assertMatchesRegularExpression(
            '#^/broker/query-results/sha256:.+#',
            $contentLocation,
            'Expected Content-Location /broker/query-results/sha256:..., got: '.$contentLocation
        );

        $json = $response->json();

        $this->assertTrue($json['ok'] ?? false, 'Expected ok=true: '.$response->body());
        $this->assertCount(2, $json['rows'] ?? [], 'Expected 2 UNKNOWN / not-safe-to-close payments');
        $this->assertStringStartsWith('sha256:', $json['query_fingerprint'] ?? '');
        $this->assertSame(0, $json['snapshot']['from_offset'] ?? null);
        $this->assertSame(3, $json['snapshot']['to_offset'] ?? null);
        $this->assertFalse($json['safety']['broker_state_mutated'] ?? true);
        $this->assertSame('bounded_partition_scan', $json['explain']['plan'] ?? null);
        $this->assertSame(3, $json['explain']['messages_scanned'] ?? null);
        $this->assertSame(2, $json['explain']['messages_matched'] ?? null);
        $this->assertFalse($json['explain']['broker_state_mutated'] ?? true);

        // Conditional revalidation: same QUERY + If-None-Match → 304
        $revalidated = $this->ayderQueryClient($base, $token)
            ->withHeaders(['If-None-Match' => $etag])
            ->query('/broker/query', $queryPayload);

        $this->assertSame(
            304,
            $revalidated->status(),
            'Expected conditional QUERY HTTP 304, got '.$revalidated->status().': '.$revalidated->body()
        );
    }

    public function test_ayder_rejects_unsupported_query_content_type(): void
    {
        [$base, $token] = $this->ayderConnectionOrSkip();

        $response = Http::baseUrl($base)
            ->withToken($token)
            ->withBody('{"source":{"topic":"missing","partition":0,"from_offset":0,"to_offset":0,"limit":1}}', 'text/plain')
            ->send('QUERY', '/broker/query');

        $this->assertSame(
            415,
            $response->status(),
            'Expected unsupported media type HTTP 415, got '.$response->status().': '.$response->body()
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function ayderConnectionOrSkip(): array
    {
        $base = rtrim(getenv('AYDER_BASE_URL') ?: 'http://127.0.0.1:1109', '/');
        $token = getenv('AYDER_TOKEN') ?: 'dev';

        if (! $this->ayderIsReachable($base)) {
            $this->markTestSkipped("Ayder is not reachable at {$base}. Start it with: docker compose up -d --build");
        }

        return [$base, $token];
    }

    protected function ayderQueryClient(string $base, string $token)
    {
        return Http::baseUrl($base)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }

    protected function ayderIsReachable(string $base): bool
    {
        try {
            $response = Http::timeout(2)->get("{$base}/health");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
