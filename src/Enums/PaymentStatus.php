<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Enums;

enum PaymentStatus: int
{
    case REGISTERED = 0;
    case COMPLETED = 1;
    case DECLINED = 2;
    case NEEDS_CLARIFICATION = 3;
    case CANCELLED = 4;

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Registered',
            self::COMPLETED => 'Completed',
            self::DECLINED => 'Declined',
            self::NEEDS_CLARIFICATION => 'Needs clarification',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this === self::REGISTERED || $this === self::NEEDS_CLARIFICATION;
    }

    public function isFailed(): bool
    {
        return $this === self::DECLINED || $this === self::CANCELLED;
    }

    public static function fromCallbackStatus(string $status): self
    {
        return match (strtoupper($status)) {
            'APPROVED' => self::COMPLETED,
            'DECLINED' => self::DECLINED,
            'NEEDS-CLARIFICATION', 'NEEDS_CLARIFICATION' => self::NEEDS_CLARIFICATION,
            'CANCELLED', 'CANCELED' => self::CANCELLED,
            default => self::REGISTERED,
        };
    }
}
