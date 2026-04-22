<?php

declare(strict_types=1);

namespace Starlabs\LaravelProcard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Starlabs\LaravelProcard\Exceptions\ProcardException;
use Starlabs\LaravelProcard\ProcardService;
use Symfony\Component\HttpFoundation\Response;

class VerifyProcardSignature
{
    public function __construct(
        protected ProcardService $procard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->procard->verifyCallbackSignature($request->all())) {
            throw ProcardException::callbackValidationFailed('Invalid merchantSignature.');
        }

        return $next($request);
    }
}
