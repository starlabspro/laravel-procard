# Procard (ProcardPay Dispatcher) payment integration for Laravel

Laravel package for the [Procard Dispatcher](http://docs.procard-ltd.com/en/docs/dispatcher/) hosted payment page integration.

This is a **sibling package** to [`starlabspro/laravel-paysera`](https://github.com/starlabspro/laravel-paysera). Procard is a Ukrainian card acquirer; the API and signing scheme are entirely different from Paysera's WebToPay flow, so the two are kept as separate packages.

## Requirements

- PHP 8.2+
- Laravel 10.0+ / 11.0+ / 12.0+
- Procard merchant account (merchant id, secret key, API subdomain)

## Installation

```bash
composer require starlabspro/laravel-procard
```

Publish the config and migration:

```bash
php artisan vendor:publish --tag="laravel-procard-config"
php artisan vendor:publish --tag="laravel-procard-migrations"
php artisan migrate
```

## Configuration

```env
PROCARD_BASE_URL=https://your-subdomain.procard-ltd.com/api/
PROCARD_MERCHANT_ID=your_merchant_id
PROCARD_SECRET_KEY=your_secret_key
PROCARD_CURRENCY=EUR
PROCARD_LANGUAGE=en

PROCARD_APPROVE_URL=https://yoursite.com/payment/success
PROCARD_DECLINE_URL=https://yoursite.com/payment/declined
PROCARD_CANCEL_URL=https://yoursite.com/payment/cancel
PROCARD_CALLBACK_URL=https://yoursite.com/procard/callback
```

## How the Procard flow differs from Paysera

| Step | Paysera (Checkout Classic) | Procard (Dispatcher) |
|---|---|---|
| Kick off | Browser `GET` to `pay.paysera.com/pay/` with `data=` + `sign=` | Server `POST` JSON to `https://<sub>.procard-ltd.com/api/` |
| Signing | MD5 over `data+password` (handled by SDK) | HMAC-SHA512 over `merchant_id;order_id;amount;currency_iso;description` |
| Redirect | Paysera renders bank list directly | API returns `{"result":0,"url":"…"}`; you redirect the user to that URL |
| Callback | Form POST, `ss1`/`ss2` verification | JSON POST, `merchantSignature` HMAC-SHA512 |
| Amount | Sent as integer minor units (e.g. `2999`) | Sent as decimal string, trailing zeros stripped (`100.00` → `100`) |

## API Mode (Vue / SPA)

Set `PROCARD_API_MODE=true` to enable API mode.

```js
const response = await axios.post('/api/procard/initiate', {
    order_id: order.id,
    amount: order.total,
    description: `Order #${order.id}`,
    email: user.email,
})

window.location.href = response.data.payment_url
```

## SSR / Blade

```php
use Starlabs\LaravelProcard\Facades\Procard;

$result = Procard::initiate(
    orderId: (string) $order->id,
    amount: $order->total,
    description: "Order #{$order->id}",
    email: $order->user_email,
);

return redirect()->away($result['payment_url']);
```

## Callback / Webhook

The callback signature is verified via HMAC-SHA512 over `merchant_id;orderReference;amount;currency`.

```php
use Starlabs\LaravelProcard\Facades\Procard;
use Starlabs\LaravelProcard\Models\ProcardTransaction;

Route::post('/procard/callback', function (Request $request) {
    $result = Procard::handleCallback($request);

    if ($result->isCompleted()) {
        $transaction = ProcardTransaction::findByOrderId($result->orderId);
        // fulfil the order
    }

    return 'OK';
});
```

## Events

```php
use Starlabs\LaravelProcard\Events\PaymentCompleted;
use Starlabs\LaravelProcard\Events\PaymentCancelled;
use Starlabs\LaravelProcard\Events\PaymentDeclined;
use Starlabs\LaravelProcard\Events\PaymentReceived;

protected $listen = [
    PaymentCompleted::class => [SendConfirmationEmail::class],
    PaymentDeclined::class => [LogDecline::class],
    PaymentCancelled::class => [LogCancellation::class],
    PaymentReceived::class => [ProcessPaymentNotification::class],
];
```

## Payment Status Enum

| Enum Case | Value | Meaning |
|---|---|---|
| `PaymentStatus::REGISTERED` | 0 | Registered locally, awaiting gateway |
| `PaymentStatus::COMPLETED` | 1 | `transactionStatus = Approved` |
| `PaymentStatus::DECLINED` | 2 | `transactionStatus = Declined` |
| `PaymentStatus::NEEDS_CLARIFICATION` | 3 | `transactionStatus = NEEDS-CLARIFICATION` |
| `PaymentStatus::CANCELLED` | 4 | User cancelled (or local cancel) |

## Routes

### SSR mode (default)

| Method | URI | Description |
|---|---|---|
| POST | `/procard/callback` | Procard server callback |
| GET | `/procard/approve` | Redirect on success |
| GET | `/procard/decline` | Redirect on decline |
| GET | `/procard/cancel` | Redirect on cancel |

### API mode (`PROCARD_API_MODE=true`)

| Method | URI | Description |
|---|---|---|
| POST | `/api/procard/initiate` | Create payment, returns URL |
| POST | `/api/procard/callback` | Procard server callback |
| GET | `/api/procard/approve` | JSON with redirect URL |
| GET | `/api/procard/decline` | JSON with redirect URL |
| GET | `/api/procard/cancel` | JSON with redirect URL |

Disable auto-routes:

```env
PROCARD_ROUTES_ENABLED=false
```

## License

The MIT License (MIT). See [License File](LICENSE.md).
