<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\DTOs;

use Starlabs\LaravelProcard\Enums\PaymentStatus;

class PaymentResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId,
        public readonly ?string $orderId,
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly ?string $cardPan,
        public readonly ?string $cardType,
        public readonly ?string $reason,
        public readonly ?string $reasonCode,
        public readonly array $rawData,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isDeclined(): bool
    {
        return $this->status === PaymentStatus::DECLINED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::CANCELLED;
    }

    public function needsClarification(): bool
    {
        return $this->status === PaymentStatus::NEEDS_CLARIFICATION;
    }

    public static function fromCallbackData(array $data, ?string $internalOrderId = null): self
    {
        return new self(
            status: PaymentStatus::fromCallbackStatus((string) ($data['transactionStatus'] ?? '')),
            transactionId: isset($data['transactionId']) ? (string) $data['transactionId'] : null,
            orderId: $internalOrderId ?? ($data['orderReference'] ?? null),
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            cardPan: $data['cardPan'] ?? null,
            cardType: $data['cardType'] ?? null,
            reason: $data['reason'] ?? null,
            reasonCode: isset($data['reasonCode']) ? (string) $data['reasonCode'] : null,
            rawData: $data,
        );
    }
}
