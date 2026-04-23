<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Starlabs\LaravelProcard\Enums\PaymentStatus;
use Starlabs\LaravelProcard\Exceptions\ProcardException;
use Starlabs\LaravelProcard\Models\ProcardTransaction;
use Starlabs\LaravelProcard\ProcardService;

class ProcardServiceTest extends TestCase
{
    #[Test]
    public function it_throws_exception_when_merchant_id_missing(): void
    {
        config(['procard.merchant_id' => null]);

        $this->expectException(ProcardException::class);
        $this->expectExceptionMessage("'merchant_id' is missing");

        $service = new ProcardService;
        $service->buildRequestPayload([
            'order_id' => 'TEST-1',
            'amount' => 10.00,
            'description' => 'Test',
        ]);
    }

    #[Test]
    public function it_throws_exception_when_secret_key_missing(): void
    {
        config(['procard.secret_key' => null]);

        $this->expectException(ProcardException::class);
        $this->expectExceptionMessage("'secret_key' is missing");

        $service = new ProcardService;
        $service->buildRequestPayload([
            'order_id' => 'TEST-1',
            'amount' => 10.00,
            'description' => 'Test',
        ]);
    }

    #[Test]
    public function it_creates_transaction(): void
    {
        Event::fake();

        $service = new ProcardService;
        $transaction = $service->createTransaction(
            orderId: 'ORDER-TEST-1',
            amount: 29.99,
            description: 'Test order',
            email: 'test@example.com',
        );

        $this->assertInstanceOf(ProcardTransaction::class, $transaction);
        $this->assertEquals('ORDER-TEST-1', $transaction->order_id);
        $this->assertEquals(29.99, $transaction->amount);
        $this->assertEquals('EUR', $transaction->currency);
        $this->assertEquals('test@example.com', $transaction->payer_email);
        $this->assertEquals(PaymentStatus::REGISTERED, $transaction->status);
    }

    #[Test]
    public function it_formats_amount_by_stripping_trailing_zeros(): void
    {
        $this->assertSame('100', ProcardService::formatAmount(100.00));
        $this->assertSame('100.5', ProcardService::formatAmount(100.50));
        $this->assertSame('100.01', ProcardService::formatAmount(100.01));
        $this->assertSame('0', ProcardService::formatAmount(0.00));
    }

    #[Test]
    public function it_builds_signature_with_documented_formula(): void
    {
        $service = new ProcardService;

        $expected = hash_hmac(
            'sha512',
            'MERCH-TEST;ORDER-1;100;EUR;Test order',
            'secret_key_value',
        );

        $this->assertSame(
            $expected,
            $service->calculateSignature(['MERCH-TEST', 'ORDER-1', '100', 'EUR', 'Test order']),
        );
    }

    #[Test]
    public function build_request_payload_produces_expected_fields_and_signature(): void
    {
        $service = new ProcardService;

        $payload = $service->buildRequestPayload([
            'order_id' => 'ORDER-1',
            'amount' => 100.00,
            'description' => 'Test order',
        ]);

        $this->assertSame('Purchase', $payload['operation']);
        $this->assertSame('MERCH-TEST', $payload['merchant_id']);
        $this->assertSame('ORDER-1', $payload['order_id']);
        $this->assertSame('100', $payload['amount']);
        $this->assertSame('EUR', $payload['currency_iso']);
        $this->assertSame('Test order', $payload['description']);
        $this->assertSame(
            hash_hmac('sha512', 'MERCH-TEST;ORDER-1;100;EUR;Test order', 'secret_key_value'),
            $payload['signature'],
        );
    }

    #[Test]
    public function initiate_returns_signed_form_fields_and_base_url(): void
    {
        Event::fake();
        Http::preventStrayRequests();

        $service = new ProcardService;
        $result = $service->initiate(
            orderId: 'ORDER-HTTP-1',
            amount: 50.00,
            description: 'Test order',
            email: 'buyer@example.com',
        );

        $this->assertSame('https://example.procard-ltd.com/api/', $result['payment_url']);
        $this->assertSame('ORDER-HTTP-1', $result['order_id']);
        $this->assertNotNull($result['transaction_id']);
        $this->assertStringStartsWith('ORDER-HTTP-1-', $result['external_order_id']);

        $fields = $result['payment_fields'];
        $this->assertSame('Purchase', $fields['operation']);
        $this->assertSame($result['external_order_id'], $fields['order_id']);
        $this->assertSame('50', $fields['amount']);
        $this->assertSame('EUR', $fields['currency_iso']);
        $this->assertNotEmpty($fields['signature']);
    }

    #[Test]
    public function initiate_twice_for_same_order_produces_distinct_external_order_ids(): void
    {
        Event::fake();

        $service = new ProcardService;

        $first = $service->initiate(
            orderId: 'INV-DUP-1',
            amount: 10.00,
            description: 'First attempt',
        );
        $second = $service->initiate(
            orderId: 'INV-DUP-1',
            amount: 10.00,
            description: 'Second attempt',
        );

        $this->assertNotSame($first['external_order_id'], $second['external_order_id']);
        $this->assertSame(2, ProcardTransaction::where('order_id', 'INV-DUP-1')->count());
    }

    #[Test]
    public function send_purchase_request_throws_when_procard_returns_error_result(): void
    {
        Http::fake([
            '*procard-ltd.com/api/*' => Http::response([
                'result' => 1,
                'code' => 99,
                'message' => 'Invalid signature',
            ]),
        ]);

        $this->expectException(ProcardException::class);
        $this->expectExceptionMessage('Procard returned error 99: Invalid signature');

        $service = new ProcardService;
        $service->sendPurchaseRequest([
            'merchant_id' => 'MERCH-TEST',
            'order_id' => 'ORDER-ERR-1',
            'amount' => '10',
            'currency_iso' => 'EUR',
            'description' => 'Err',
        ]);
    }

    #[Test]
    public function verify_callback_signature_passes_for_valid_signature(): void
    {
        $service = new ProcardService;

        $signature = hash_hmac(
            'sha512',
            'MERCH-TEST;ORDER-VERIFY;25.5;EUR',
            'secret_key_value',
        );

        $this->assertTrue($service->verifyCallbackSignature([
            'orderReference' => 'ORDER-VERIFY',
            'amount' => '25.50',
            'currency' => 'EUR',
            'merchantSignature' => $signature,
        ]));
    }

    #[Test]
    public function verify_callback_signature_fails_for_bad_signature(): void
    {
        $service = new ProcardService;

        $this->assertFalse($service->verifyCallbackSignature([
            'orderReference' => 'ORDER-VERIFY',
            'amount' => '25.50',
            'currency' => 'EUR',
            'merchantSignature' => 'not-a-valid-signature',
        ]));
    }

    #[Test]
    public function payment_status_enum_has_helper_methods(): void
    {
        $this->assertTrue(PaymentStatus::COMPLETED->isSuccessful());
        $this->assertTrue(PaymentStatus::REGISTERED->isPending());
        $this->assertTrue(PaymentStatus::NEEDS_CLARIFICATION->isPending());
        $this->assertFalse(PaymentStatus::DECLINED->isSuccessful());
        $this->assertTrue(PaymentStatus::DECLINED->isFailed());
        $this->assertTrue(PaymentStatus::CANCELLED->isFailed());
    }
}
