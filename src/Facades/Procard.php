<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Facades;

use Illuminate\Support\Facades\Facade;
use Starlabs\LaravelProcard\ProcardService;

/**
 * @method static array buildRequestPayload(array $data)
 * @method static array sendPurchaseRequest(array $payload)
 * @method static \Starlabs\LaravelProcard\DTOs\PaymentResult handleCallback(\Illuminate\Http\Request $request)
 * @method static \Starlabs\LaravelProcard\Models\ProcardTransaction createTransaction(string $orderId, float $amount, string $description = '', ?string $email = null, array $extraData = [])
 * @method static array initiate(string $orderId, float $amount, string $description = '', ?string $email = null, array $extraData = [])
 * @method static bool verifyCallbackSignature(array $data)
 * @method static string calculateSignature(array $parts)
 * @method static self setBaseUrl(string $baseUrl)
 * @method static self setMerchantId(string $merchantId)
 * @method static self setSecretKey(string $secretKey)
 * @method static self setCurrency(string $currency)
 * @method static self setLanguage(string $language)
 *
 * @see ProcardService
 */
class Procard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ProcardService::class;
    }
}
