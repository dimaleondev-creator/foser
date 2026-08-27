<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureDashboardRole;
use App\Http\Middleware\EnsureUniversityResponsible;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'role.dashboard' => EnsureDashboardRole::class,
            'university.access' => EnsureUniversityResponsible::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request): string => route('student.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $response = ['message' => $status === 500 ? 'Une erreur interne est survenue.' : $exception->getMessage()];

            if ($exception instanceof ValidationException) {
                $status = 422;
                $response['message'] = 'Les données fournies sont invalides.';
                $response['errors'] = $exception->errors();
            }

            return response()->json($response, $status);
        });
    })->create();
