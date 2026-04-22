<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Starlabs\LaravelProcard\Enums\PaymentStatus;
use Starlabs\LaravelProcard\Events\PaymentCancelled;
use Starlabs\LaravelProcard\Events\PaymentCompleted;
use Starlabs\LaravelProcard\Events\PaymentDeclined;

/**
 * @property int $id
 * @property string $order_id
 * @property string|null $order_description
 * @property float $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $procard_transaction_id
 * @property string|null $payer_email
 * @property string|null $card_pan
 * @property string|null $card_type
 * @property string|null $language
 * @property string|null $reason
 * @property string|null $reason_code
 * @property array|null $raw_response
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProcardTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'order_description',
        'amount',
        'currency',
        'status',
        'procard_transaction_id',
        'payer_email',
        'card_pan',
        'card_type',
        'language',
        'reason',
        'reason_code',
        'raw_response',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'status' => PaymentStatus::class,
        'raw_response' => 'array',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => PaymentStatus::REGISTERED,
    ];

    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [PaymentStatus::REGISTERED, PaymentStatus::NEEDS_CLARIFICATION]);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', [PaymentStatus::CANCELLED, PaymentStatus::DECLINED]);
    }

    public function markAsCompleted(): self
    {
        $this->update([
            'status' => PaymentStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        PaymentCompleted::dispatch($this);

        return $this;
    }

    public function markAsCancelled(): self
    {
        $this->update(['status' => PaymentStatus::CANCELLED]);

        PaymentCancelled::dispatch($this);

        return $this;
    }

    public function markAsDeclined(): self
    {
        $this->update(['status' => PaymentStatus::DECLINED]);

        PaymentDeclined::dispatch($this);

        return $this;
    }

    public function markAsNeedsClarification(): self
    {
        $this->update(['status' => PaymentStatus::NEEDS_CLARIFICATION]);

        return $this;
    }

    public static function findByOrderId(string $orderId): ?self
    {
        return static::where('order_id', $orderId)->first();
    }

    public static function findByProcardTransactionId(string $transactionId): ?self
    {
        return static::where('procard_transaction_id', $transactionId)->first();
    }
}
