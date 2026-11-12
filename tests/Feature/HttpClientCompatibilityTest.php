<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpClientCompatibilityTest extends TestCase
{
    public function test_laravel_http_client_can_send_a_request_through_guzzle(): void
    {
        Http::fake(['example.test/*' => Http::response(['ok' => true], 200)]);
        $response = Http::get('https://example.test/status');

        $this->assertTrue($response->successful());
        $this->assertTrue($response->json('ok'));
        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/status');
    }
}
