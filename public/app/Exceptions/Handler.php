<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        OAuthServerException::class,
        InvalidTokenStructure::class,
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log exception if needed
        });
    }

    /**
     * Render an exception into an HTTP response — ALWAYS JSON.
     */
    public function render($request, Throwable $e)
    {
        // Only force JSON for API routes
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert exception to response — bypass HTML views.
     */
    protected function convertExceptionToResponse(Throwable $e)
    {
        return $this->render(request(), $e);
    }

    private function handleApiException($request, Throwable $e)
    {
        $response = [
            'success' => false,
            'message' => 'An error occurred.',
            'data' => null,
        ];

        if ($e instanceof ValidationException) {
            return $this->invalidJson($request, $e);
        }

        if ($e instanceof ModelNotFoundException) {
            $response['message'] = 'Resource not found.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 404);
        }

        if ($e instanceof NotFoundHttpException) {
            $response['message'] = 'Endpoint not found.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 404);
        }

        if ($e instanceof OAuthServerException || $e instanceof InvalidTokenStructure) {
            $response['message'] = 'Unauthorized. Token may be missing, invalid, or expired.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 401);
        }

        if ($e instanceof AuthenticationException) {
            $response['message'] = 'Unauthenticated.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 401);
        }

        if ($e instanceof UnauthorizedHttpException) {
            $response['message'] = 'Unauthorized. Token may be invalid or expired.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 401);
        }

        if ($e instanceof AccessDeniedHttpException) {
            $response['message'] = 'Access denied.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 403);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            $response['message'] = 'Method not allowed.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 405);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            $response['message'] = 'Too many requests. Please try again later.';
            return new JsonResponse($response, 429);
        }

        if ($e instanceof HttpResponseException) {
            $response['data'] = $e->getMessage();
            return $e->getResponse();
        }

        if ($e instanceof \Illuminate\Session\TokenMismatchException) {
            $response['message'] = 'CSRF token mismatch. Please retry your request.';
            $response['data'] = $e->getMessage();
            return new JsonResponse($response, 419);
        }

        // 500 — Internal Server Error
        if (config('app.debug')) {
            $response['message'] = $e->getMessage();
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = $e->getTraceAsString();
        } else {
            $response['message'] = 'Internal server error.';
        }

        return new JsonResponse($response, 500);
    }

    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $exception->errors(),
        ], $exception->status);
    }
}
