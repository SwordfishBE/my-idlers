<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_test/proxy', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'url' => $request->getSchemeAndHttpHost(),
        ]));
    }

    public function test_local_proxy_can_forward_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['Host' => 'idlers.locutus.net', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('http://idlers.locutus.net/_test/proxy')
            ->assertOk()->assertJson(['secure' => true, 'url' => 'https://idlers.locutus.net']);
    }

    public function test_untrusted_client_cannot_spoof_forwarded_scheme(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeaders(['Host' => 'idlers.locutus.net', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('http://idlers.locutus.net/_test/proxy')
            ->assertOk()->assertJson(['secure' => false, 'url' => 'http://idlers.locutus.net']);
    }
}
