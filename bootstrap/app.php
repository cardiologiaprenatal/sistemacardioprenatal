<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Necessário quando o Laravel fica atrás de proxy TLS (Nginx/Caddy).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'whatsapp.webhook' => \App\Http\Middleware\VerifyGestanteWhatsappWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 419) {
                $message = 'Não foi possível concluir a tentativa. Tente novamente.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 419);
                }

                return response($message, 419);
            }

            $authRequest = $request->is(
                'login',
                'logout',
                'users',
                'users/*',
                'usuarios',
                'usuarios/*',
                'esqueci-senha',
                'esqueci-senha/*',
                'redefinir-senha',
                'redefinir-senha/*',
            );

            if (! $authRequest || ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = match ($status) {
                    429 => 'Muitas tentativas. Aguarde um minuto e tente novamente.',
                    403 => 'Acesso não permitido.',
                    default => 'Não foi possível concluir a solicitação.',
                };

                return response()->json(['message' => $message], $status);
            }

            return response()->json([
                'message' => 'Erro interno no servidor.',
            ], 500);
        });
    })->create();
