<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Starlabs\LaravelProcard\Exceptions\ProcardException;
use Starlabs\LaravelProcard\ProcardService;
use Throwable;

class ProcardController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function __construct(
        protected ProcardService $procard,
    ) {}

    public function callback(Request $request): string
    {
        try {
            $this->procard->handleCallback($request);
        } catch (Throwable $e) {
            report($e);

            return 'ERROR';
        }

        return 'OK';
    }

    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:1000'],
            'email' => ['nullable', 'email'],
            'language' => ['nullable', 'string', 'max:5'],
            'client_first_name' => ['nullable', 'string', 'max:255'],
            'client_last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $data = $this->procard->initiate(
                orderId: $validated['order_id'],
                amount: (float) $validated['amount'],
                description: $validated['description'],
                email: $validated['email'] ?? null,
                extraData: array_filter([
                    'language' => $validated['language'] ?? null,
                    'client_first_name' => $validated['client_first_name'] ?? null,
                    'client_last_name' => $validated['client_last_name'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                ], static fn ($v) => $v !== null),
            );
        } catch (ProcardException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($data);
    }

    public function approve(Request $request): RedirectResponse|JsonResponse
    {
        if (config('procard.api.enabled')) {
            return response()->json([
                'redirect_url' => config('procard.urls.approve_url'),
                'status' => 'approved',
            ]);
        }

        $redirectUrl = config('procard.urls.approve_url') ?? '/';

        return redirect()->to($redirectUrl)->with('procard_status', 'approved');
    }

    public function decline(Request $request): RedirectResponse|JsonResponse
    {
        if (config('procard.api.enabled')) {
            return response()->json([
                'redirect_url' => config('procard.urls.decline_url'),
                'status' => 'declined',
            ]);
        }

        $redirectUrl = config('procard.urls.decline_url') ?? '/';

        return redirect()->to($redirectUrl)->with('procard_status', 'declined');
    }

    public function cancel(Request $request): RedirectResponse|JsonResponse
    {
        if (config('procard.api.enabled')) {
            return response()->json([
                'redirect_url' => config('procard.urls.cancel_url'),
                'status' => 'cancelled',
            ]);
        }

        $redirectUrl = config('procard.urls.cancel_url') ?? '/';

        return redirect()->to($redirectUrl)->with('procard_status', 'cancelled');
    }
}
