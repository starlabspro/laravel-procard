<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Starlabs\LaravelProcard\Models\ProcardTransaction;

class PaymentCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ProcardTransaction $transaction,
    ) {}
}
