<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Tests;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Starlabs\LaravelProcard\Enums\PaymentStatus;
use Starlabs\LaravelProcard\Events\PaymentCancelled;
use Starlabs\LaravelProcard\Events\PaymentCompleted;
use Starlabs\LaravelProcard\Events\PaymentDeclined;
use Starlabs\LaravelProcard\Models\ProcardTransaction;

class ProcardTransactionTest extends TestCase
{
    #[Test]
    public function it_creates_transaction_with_defaults(): void
    {
        ProcardTransaction::create([
            'order_id' => 'ORDER-001',
            'amount' => 29.99,
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('procard_transactions', [
            'order_id' => 'ORDER-001',
            'amount' => 29.99,
            'currency' => 'EUR',
            'status' => PaymentStatus::REGISTERED->value,
        ]);
    }

    #[Test]
    public function it_marks_transaction_as_completed(): void
    {
        $transaction = ProcardTransaction::create([
            'order_id' => 'ORDER-002',
            'amount' => 49.99,
            'currency' => 'EUR',
        ]);

        Event::fake([PaymentCompleted::class]);

        $transaction->markAsCompleted();

        $this->assertEquals(PaymentStatus::COMPLETED, $transaction->fresh()->status);
        $this->assertNotNull($transaction->fresh()->completed_at);

        Event::assertDispatched(PaymentCompleted::class);
    }

    #[Test]
    public function it_marks_transaction_as_cancelled(): void
    {
        $transaction = ProcardTransaction::create([
            'order_id' => 'ORDER-003',
            'amount' => 19.99,
            'currency' => 'EUR',
        ]);

        Event::fake([PaymentCancelled::class]);

        $transaction->markAsCancelled();

        $this->assertEquals(PaymentStatus::CANCELLED, $transaction->fresh()->status);

        Event::assertDispatched(PaymentCancelled::class);
    }

    #[Test]
    public function it_marks_transaction_as_declined(): void
    {
        $transaction = ProcardTransaction::create([
            'order_id' => 'ORDER-004',
            'amount' => 19.99,
            'currency' => 'EUR',
        ]);

        Event::fake([PaymentDeclined::class]);

        $transaction->markAsDeclined();

        $this->assertEquals(PaymentStatus::DECLINED, $transaction->fresh()->status);

        Event::assertDispatched(PaymentDeclined::class);
    }

    #[Test]
    public function it_finds_by_order_id(): void
    {
        ProcardTransaction::create([
            'order_id' => 'ORDER-005',
            'amount' => 9.99,
            'currency' => 'EUR',
        ]);

        $found = ProcardTransaction::findByOrderId('ORDER-005');

        $this->assertNotNull($found);
        $this->assertEquals('ORDER-005', $found->order_id);
    }

    #[Test]
    public function scopes_filter_correctly(): void
    {
        ProcardTransaction::create(['order_id' => 'ORD-1', 'amount' => 10, 'currency' => 'EUR', 'status' => PaymentStatus::COMPLETED]);
        ProcardTransaction::create(['order_id' => 'ORD-2', 'amount' => 20, 'currency' => 'EUR', 'status' => PaymentStatus::REGISTERED]);
        ProcardTransaction::create(['order_id' => 'ORD-3', 'amount' => 30, 'currency' => 'EUR', 'status' => PaymentStatus::CANCELLED]);
        ProcardTransaction::create(['order_id' => 'ORD-4', 'amount' => 40, 'currency' => 'EUR', 'status' => PaymentStatus::DECLINED]);

        $this->assertEquals(1, ProcardTransaction::completed()->count());
        $this->assertEquals(1, ProcardTransaction::pending()->count());
        $this->assertEquals(2, ProcardTransaction::failed()->count());
    }
}
