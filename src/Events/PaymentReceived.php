<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Starlabs\LaravelProcard\Enums\PaymentStatus;
use Starlabs\LaravelProcard\Models\ProcardTransaction;

class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ?ProcardTransaction $transaction,
        public readonly PaymentStatus $status,
    ) {}
}
