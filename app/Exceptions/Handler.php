<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Lista wyjątków, które nie będą raportowane.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * Lista pól, które nigdy nie będą pokazywane w komunikatach błędów walidacji.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Zgłoś wyjątek.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Obsługa błędu throttle (429)
        $this->renderable(function (ThrottleRequestsException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . $e->getHeaders()['Retry-After'] . ' sekund.',
                ], 429);
            }
        });
    }

    /**
     * Obsługa błędu uwierzytelnienia.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Nieautoryzowany dostęp.'], 401);
        }

        return redirect()->guest(route('login'));
    }
}