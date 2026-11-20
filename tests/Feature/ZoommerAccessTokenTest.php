<?php

namespace Tests\Feature;

use App\Services\Import\Drivers\Zoommer\BlockedByZoommer;
use App\Services\Import\Drivers\Zoommer\ZoommerClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ZoommerAccessTokenTest extends TestCase
{
    public function test_a_token_copied_from_the_cookie_is_sent_as_the_cookie_unchanged(): void
    {
        config(['services.zoommer.access_token' => 'NV%2Fab%2Bcd%3D']);

        $this->assertSame('zoommer-cookie_agreed=true; zoommer-access_token=NV%2Fab%2Bcd%3D', ZoommerClient::cookie());
    }

    public function test_a_decoded_or_prefixed_token_is_normalised_to_the_cookie_form(): void
    {
        config(['services.zoommer.access_token' => ' "Bearer NV/ab+cd=" ']);
        $this->assertSame('NV%2Fab%2Bcd%3D', ZoommerClient::accessToken());

        config(['services.zoommer.access_token' => 'zoommer-access_token=NV%2Fab']);
        $this->assertSame('NV%2Fab', ZoommerClient::accessToken());
    }

    public function test_without_a_token_only_the_consent_cookie_is_sent(): void
    {
        config(['services.zoommer.access_token' => null]);

        $this->assertNull(ZoommerClient::accessToken());
        $this->assertSame('zoommer-cookie_agreed=true', ZoommerClient::cookie());
    }

    public function test_with_a_worker_the_product_is_asked_through_it(): void
    {
        config([
            'services.zoommer.access_token' => 'NV%2Fab',
            'services.zoommer.worker_url' => 'https://zoommer-proxy.example.workers.dev/',
        ]);

        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['product' => ['name' => 'Phone']]))]));
        $stack->push(Middleware::history($history));

        $client = new ZoommerClient;
        (fn () => $this->client = new Client(['handler' => $stack, 'http_errors' => false, 'headers' => $this->headers()]))->call($client);

        $this->assertSame('Phone', $client->fetch('54835', ['ka'])['ka']['product']['name']);

        $request = $history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertSame('zoommer-proxy.example.workers.dev', $request->getUri()->getHost());
        $this->assertSame(['type' => 'product', 'productId' => '54835', 'accessToken' => 'NV%2Fab'], $query);
        $this->assertSame('zoommer-cookie_agreed=true; zoommer-access_token=NV%2Fab', $request->getHeaderLine('X-Proxy-Cookie'));
    }

    public function test_without_a_worker_the_api_is_asked_directly(): void
    {
        config(['services.zoommer.worker_url' => null]);

        $this->assertNull(ZoommerClient::workerUrl());
        $this->assertSame(
            'https://zoommer.ge/api/proxy/v1/Products/details?productId=54835',
            (fn () => $this->endpoint('54835'))->call(new ZoommerClient(['api_url' => 'https://zoommer.ge/api/proxy/'])),
        );
    }

    public function test_every_request_is_logged_without_the_token_and_refusals_keep_the_reply(): void
    {
        config(['services.zoommer.access_token' => 'secret%2Ftoken']);
        Log::shouldReceive('channel')->with('import')->andReturnSelf();
        Log::shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $context): bool {
            return $level === 'warning'
                && $context['status'] === 401
                && $context['token_sent'] === true
                && $context['response'] === 'Unauthorized'
                && ! str_contains(json_encode($context), 'secret');
        });

        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(401, [], 'Unauthorized')]));
        $stack->push(Middleware::history($history));

        $client = new ZoommerClient;
        (fn () => $this->client = new Client(['handler' => $stack, 'http_errors' => false, 'headers' => $this->headers()]))->call($client);

        try {
            $client->fetch('123', ['ka']);
            $this->fail('A 401 should stop the import.');
        } catch (BlockedByZoommer $exception) {
            $this->assertStringContainsString('HTTP 401', $exception->getMessage());
        }

        $this->assertSame('zoommer-cookie_agreed=true; zoommer-access_token=secret%2Ftoken', $history[0]['request']->getHeaderLine('Cookie'));
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
    }
}
