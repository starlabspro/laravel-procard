<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Tests;

use PHPUnit\Framework\Attributes\Test;
use Starlabs\LaravelProcard\DTOs\PaymentResult;
use Starlabs\LaravelProcard\Enums\PaymentStatus;

class PaymentResultTest extends TestCase
{
    #[Test]
    public function it_creates_from_callback_data(): void
    {
        $data = [
            'transactionStatus' => 'Approved',
            'transactionId' => '98765',
            'orderReference' => 'ORDER-123',
            'amount' => '29.99',
            'currency' => 'EUR',
            'cardPan' => '403021******9287',
            'cardType' => 'Visa',
            'reason' => 'Ok',
            'reasonCode' => '1000',
        ];

        $result = PaymentResult::fromCallbackData($data);

        $this->assertEquals(PaymentStatus::COMPLETED, $result->status);
        $this->assertEquals('98765', $result->transactionId);
        $this->assertEquals('ORDER-123', $result->orderId);
        $this->assertEquals(29.99, $result->amount);
        $this->assertEquals('EUR', $result->currency);
        $this->assertEquals('403021******9287', $result->cardPan);
        $this->assertEquals('Visa', $result->cardType);
    }

    #[Test]
    public function it_prefers_internal_order_id_when_provided(): void
    {
        $result = PaymentResult::fromCallbackData(
            ['transactionStatus' => 'Approved', 'orderReference' => 'EXT-REF-abc'],
            internalOrderId: 'INV-2026-0001',
        );

        $this->assertSame('INV-2026-0001', $result->orderId);
    }

    #[Test]
    public function it_has_status_check_methods(): void
    {
        $completed = PaymentResult::fromCallbackData(['transactionStatus' => 'Approved']);
        $declined = PaymentResult::fromCallbackData(['transactionStatus' => 'Declined']);
        $needsClarification = PaymentResult::fromCallbackData(['transactionStatus' => 'NEEDS-CLARIFICATION']);

        $this->assertTrue($completed->isCompleted());
        $this->assertTrue($declined->isDeclined());
        $this->assertTrue($needsClarification->needsClarification());
        $this->assertFalse($completed->isDeclined());
    }
}
