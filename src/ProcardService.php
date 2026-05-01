<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Starlabs\LaravelProcard\DTOs\PaymentResult;
use Starlabs\LaravelProcard\Enums\PaymentStatus;
use Starlabs\LaravelProcard\Events\PaymentReceived;
use Starlabs\LaravelProcard\Exceptions\ProcardException;
use Starlabs\LaravelProcard\Models\ProcardTransaction;

class ProcardService
{
    protected ?string $baseUrl = null;

    protected ?string $merchantId = null;

    protected ?string $secretKey = null;

    protected string $currency = 'EUR';

    protected string $language = 'en';

    protected int $httpTimeout = 15;

    protected array $defaultData = [];

    public function __construct()
    {
        $this->baseUrl = config('procard.base_url');
        $this->merchantId = config('procard.merchant_id');
        $this->secretKey = config('procard.secret_key');
        $this->currency = config('procard.currency');
        $this->language = config('procard.language');
        $this->httpTimeout = config('procard.http.timeout');
        $this->defaultData = [
            'currency_iso' => $this->currency,
            'language' => $this->language,
            'approve_url' => config('procard.urls.approve_url'),
            'decline_url' => config('procard.urls.decline_url'),
            'cancel_url' => config('procard.urls.cancel_url'),
            'callback_url' => config('procard.urls.callback_url'),
            'auth_type' => config('procard.defaults.auth_type'),
            'secure_type' => config('procard.defaults.secure_type'),
        ];
    }

    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function setMerchantId(string $merchantId): self
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    public function setSecretKey(string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function initiate(
        string $orderId,
        float $amount,
        string $description = '',
        ?string $email = null,
        array $extraData = [],
    ): array {
        $this->ensureCredentials();

        if (empty($this->baseUrl)) {
            throw ProcardException::configMissing('base_url');
        }

        $transaction = $this->createTransaction($orderId, $amount, $description, $email, $extraData);

        $payload = $this->buildRequestPayload([
            'order_id' => $transaction->external_order_id,
            'amount' => $amount,
            'description' => $description,
            'email' => $email,
        ] + $extraData);

        return [
            'payment_url' => $this->baseUrl,
            'payment_fields' => $payload,
            'transaction_id' => $transaction->id,
            'order_id' => $orderId,
            'external_order_id' => $transaction->external_order_id,
        ];
    }

    public function buildRequestPayload(array $data): array
    {
        $this->ensureCredentials();

        $payload = array_merge($this->defaultData, [
            'operation' => 'Purchase',
            'merchant_id' => $this->merchantId,
        ], $this->mapExternalKeys($data));

        $payload = array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== '',
        );

        if (! isset($payload['order_id'], $payload['amount'], $payload['currency_iso'], $payload['description'])) {
            throw ProcardException::requestFailed(
                'Missing required fields: order_id, amount, currency_iso, description.',
            );
        }

        $payload['amount'] = self::formatAmount((float) $payload['amount']);

        $payload['signature'] = $this->calculateSignature([
            (string) $payload['merchant_id'],
            (string) $payload['order_id'],
            (string) $payload['amount'],
            (string) $payload['currency_iso'],
            (string) $payload['description'],
        ]);

        return $payload;
    }

    public function sendPurchaseRequest(array $payload): array
    {
        $this->ensureCredentials();

        if (empty($this->baseUrl)) {
            throw ProcardException::configMissing('base_url');
        }

        try {
            $response = Http::timeout($this->httpTimeout)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl, $payload);
        } catch (ConnectionException $e) {
            throw ProcardException::requestFailed($e->getMessage());
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw ProcardException::requestFailed($e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw ProcardException::invalidResponse('Response body is not JSON.');
        }

        $errorCode = $body['result'] ?? $body['code'] ?? null;

        if ($errorCode !== null && (int) $errorCode !== 0) {
            $message = $body['message'] ?? 'unknown error';

            throw ProcardException::requestFailed("Procard returned error {$errorCode}: {$message}");
        }

        if (empty($body['url'])) {
            throw ProcardException::invalidResponse('Response is missing payment URL.');
        }

        return $body;
    }

    public function reverseOrder(string $orderId): array
    {
        $this->ensureCredentials();

        if (empty($this->baseUrl)) {
            throw ProcardException::configMissing('base_url');
        }

        $payload = [
            'merchant_id' => $this->merchantId,
            'order_id' => $orderId,
            'signature' => $this->calculateSignature([
                (string) $this->merchantId,
                $orderId,
            ]),
        ];

        try {
            $response = Http::timeout($this->httpTimeout)
                ->acceptJson()
                ->asJson()
                ->post(rtrim((string) $this->baseUrl, '/').'/reverse', $payload);
        } catch (ConnectionException $e) {
            throw ProcardException::requestFailed($e->getMessage());
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw ProcardException::requestFailed($e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw ProcardException::invalidResponse('Response body is not JSON.');
        }

        $code = $body['code'] ?? null;

        if ((int) $code !== 1) {
            $message = $body['message'] ?? 'unknown error';

            throw ProcardException::requestFailed("Procard reverse returned error {$code}: {$message}");
        }

        return $body;
    }

    public function checkStatus(string $orderId): array
    {
        $this->ensureCredentials();

        if (empty($this->baseUrl)) {
            throw ProcardException::configMissing('base_url');
        }

        $payload = [
            'merchant_id' => $this->merchantId,
            'order_id' => $orderId,
            'signature' => $this->calculateSignature([
                (string) $this->merchantId,
                $orderId,
            ]),
        ];

        try {
            $response = Http::timeout($this->httpTimeout)
                ->acceptJson()
                ->asJson()
                ->post(rtrim((string) $this->baseUrl, '/').'/check', $payload);
        } catch (ConnectionException $e) {
            throw ProcardException::requestFailed($e->getMessage());
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw ProcardException::requestFailed($e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw ProcardException::invalidResponse('Response body is not JSON.');
        }

        return $body;
    }

    public function createTransaction(
        string $orderId,
        float $amount,
        string $description = '',
        ?string $email = null,
        array $extraData = [],
    ): ProcardTransaction {
        return ProcardTransaction::create([
            'order_id' => $orderId,
            'external_order_id' => $this->generateExternalOrderId($orderId),
            'order_description' => $description,
            'amount' => $amount,
            'currency' => $extraData['currency'] ?? $this->currency,
            'status' => PaymentStatus::REGISTERED,
            'payer_email' => $email,
            'language' => $extraData['language'] ?? null,
        ]);
    }

    protected function generateExternalOrderId(string $orderId): string
    {
        do {
            $candidate = $orderId . '-' . Str::upper(Str::random(8));
        } while (ProcardTransaction::where('external_order_id', $candidate)->exists());

        return $candidate;
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $this->ensureCredentials();

        $data = $request->all();

        if (! $this->verifyCallbackSignature($data)) {
            throw ProcardException::callbackValidationFailed('Invalid merchantSignature.');
        }

        $transaction = $this->resolveTransaction($data);

        $result = PaymentResult::fromCallbackData(
            $data,
            internalOrderId: $transaction?->order_id,
        );

        if ($transaction) {
            $transaction->update([
                'procard_transaction_id' => $data['transactionId'] ?? null,
                'card_pan' => $data['cardPan'] ?? $transaction->card_pan,
                'card_type' => $data['cardType'] ?? $transaction->card_type,
                'reason' => $data['reason'] ?? null,
                'reason_code' => $data['reasonCode'] ?? null,
                'raw_response' => $data,
            ]);

            $this->updateTransactionStatus($transaction, $result->status);
        }

        PaymentReceived::dispatch($transaction, $result->status);

        return $result;
    }

    public function verifyCallbackSignature(array $data): bool
    {
        $provided = $data['merchantSignature'] ?? null;

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $orderReference = (string) ($data['orderReference'] ?? '');
        $currency = (string) ($data['currency'] ?? '');
        $rawAmount = (string) ($data['amount'] ?? '');
        $strippedAmount = self::formatAmount((float) ($data['amount'] ?? 0));

        $candidates = [
            'stripped' => [(string) $this->merchantId, $orderReference, $strippedAmount, $currency],
            'raw' => [(string) $this->merchantId, $orderReference, $rawAmount, $currency],
        ];

        foreach ($candidates as $parts) {
            if (hash_equals($this->calculateSignature($parts), $provided)) {
                return true;
            }
        }

        return false;
    }

    public function calculateSignature(array $parts): string
    {
        $this->ensureCredentials();

        return hash_hmac('sha512', implode(';', $parts), (string) $this->secretKey);
    }

    public static function formatAmount(float $amount): string
    {
        $formatted = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    protected function mapExternalKeys(array $data): array
    {
        $map = [
            'orderid' => 'order_id',
            'order' => 'order_id',
            'accept_url' => 'approve_url',
        ];

        foreach ($map as $from => $to) {
            if (array_key_exists($from, $data) && ! array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }

            unset($data[$from]);
        }

        return $data;
    }

    protected function resolveTransaction(array $data): ?ProcardTransaction
    {
        $reference = $data['orderReference'] ?? null;

        if (! $reference) {
            return null;
        }

        return ProcardTransaction::findByExternalOrderId((string) $reference)
            ?? ProcardTransaction::findByOrderId((string) $reference);
    }

    protected function updateTransactionStatus(ProcardTransaction $transaction, PaymentStatus $status): void
    {
        match ($status) {
            PaymentStatus::COMPLETED => $transaction->markAsCompleted(),
            PaymentStatus::CANCELLED => $transaction->markAsCancelled(),
            PaymentStatus::DECLINED => $transaction->markAsDeclined(),
            PaymentStatus::NEEDS_CLARIFICATION => $transaction->markAsNeedsClarification(),
            default => null,
        };
    }

    protected function ensureCredentials(): void
    {
        if ($this->merchantId === null || $this->merchantId === '') {
            throw ProcardException::configMissing('merchant_id');
        }

        if ($this->secretKey === null || $this->secretKey === '') {
            throw ProcardException::configMissing('secret_key');
        }
    }
}
