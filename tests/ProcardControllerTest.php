<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Starlabs\LaravelProcard\DTOs\PaymentResult;
use Starlabs\LaravelProcard\Events\PaymentCompleted;
use Starlabs\LaravelProcard\Exceptions\ProcardException;
use Starlabs\LaravelProcard\Http\Controllers\ProcardController;
use Starlabs\LaravelProcard\ProcardService;

class ProcardControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function callback_returns_ok_on_success(): void
    {
        Event::fake([PaymentCompleted::class]);

        $mockService = $this->createMock(ProcardService::class);
        $mockService->method('handleCallback')
            ->willReturn(PaymentResult::fromCallbackData([
                'transactionStatus' => 'Approved',
                'orderReference' => 'ORDER-CB-1',
                'transactionId' => '123',
                'amount' => '50.00',
                'currency' => 'EUR',
            ]));

        $controller = new ProcardController($mockService);
        $response = $controller->callback(Request::create('/procard/callback', 'POST'));

        $this->assertEquals('OK', $response);
    }

    #[Test]
    public function callback_returns_error_on_exception(): void
    {
        $mockService = $this->createMock(ProcardService::class);
        $mockService->method('handleCallback')
            ->willThrowException(ProcardException::callbackValidationFailed('Invalid merchantSignature.'));

        $controller = new ProcardController($mockService);
        $response = $controller->callback(Request::create('/procard/callback', 'POST'));

        $this->assertEquals('ERROR', $response);
    }

    #[Test]
    public function approve_redirects_to_configured_url_in_ssr_mode(): void
    {
        config(['procard.urls.approve_url' => '/checkout/success']);
        config(['procard.api.enabled' => false]);

        $service = $this->app->make(ProcardService::class);
        $controller = new ProcardController($service);
        $response = $controller->approve(Request::create('/procard/approve', 'GET'));

        $this->assertStringEndsWith('/checkout/success', $response->getTargetUrl());
        $this->assertEquals('approved', session('procard_status'));
    }

    #[Test]
    public function cancel_redirects_to_configured_url_in_ssr_mode(): void
    {
        config(['procard.urls.cancel_url' => '/cart']);
        config(['procard.api.enabled' => false]);

        $service = $this->app->make(ProcardService::class);
        $controller = new ProcardController($service);
        $response = $controller->cancel(Request::create('/procard/cancel', 'GET'));

        $this->assertStringEndsWith('/cart', $response->getTargetUrl());
        $this->assertEquals('cancelled', session('procard_status'));
    }

    #[Test]
    public function approve_returns_json_in_api_mode(): void
    {
        config(['procard.urls.approve_url' => '/checkout/success']);
        config(['procard.api.enabled' => true]);

        $service = $this->app->make(ProcardService::class);
        $controller = new ProcardController($service);
        $response = $controller->approve(Request::create('/procard/approve', 'GET'));

        $this->assertEquals('/checkout/success', $response->getData(true)['redirect_url']);
        $this->assertEquals('approved', $response->getData(true)['status']);
    }

    #[Test]
    public function initiate_returns_payment_url_and_transaction(): void
    {
        Event::fake();

        $mockService = $this->createMock(ProcardService::class);
        $mockService->method('initiate')
            ->willReturn([
                'payment_url' => 'https://procard-ltd.com/payment/pay?payment=abc',
                'transaction_id' => 5,
                'order_id' => 'ORDER-API-1',
                'response' => ['result' => 0, 'url' => 'https://procard-ltd.com/payment/pay?payment=abc'],
            ]);

        $controller = new ProcardController($mockService);
        $request = Request::create('/api/procard/initiate', 'POST', [
            'order_id' => 'ORDER-API-1',
            'amount' => '29.99',
            'description' => 'Test',
            'email' => 'buyer@example.com',
        ]);
        $response = $controller->initiate($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('procard-ltd.com', $response->getData(true)['payment_url']);
        $this->assertEquals(5, $response->getData(true)['transaction_id']);
        $this->assertEquals('ORDER-API-1', $response->getData(true)['order_id']);
    }
}
