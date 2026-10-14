<?php

namespace App\Http\Controllers\Api\Savings\Concerns;

use App\Http\Helpers\Response;
use App\Services\Savings\SavingsException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait RespondsToSavings
{
    /** Run an action, converting domain errors into the standard error envelope. */
    protected function guarded(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (SavingsException $e) {
            return Response::errorResponse(
                $e->getMessage(),
                ['code' => $e->errorCode] + $e->context,
                $e->httpStatus
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('savings.unexpected_error', ['error' => $e->getMessage(), 'exception' => get_class($e)]);

            return Response::errorResponse('Unable to process the request right now. Please try again.', ['code' => 'SERVER_ERROR'], 500);
        }
    }

    /** Mandatory for money-moving requests: makes retries safe. */
    protected function idempotencyKey(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9_\-:.]{8,64}$/', $key)) {
            throw new SavingsException(
                'A valid Idempotency-Key header (8-64 chars: letters, digits, _ - : .) is required.',
                'IDEMPOTENCY_KEY_REQUIRED',
                422
            );
        }

        return $key;
    }

    protected function page(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 20)));
    }

    protected function paginated(LengthAwarePaginator $p, callable $map): array
    {
        return [
            'items' => collect($p->items())->map($map)->values()->all(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'last_page' => $p->lastPage(),
            ],
        ];
    }
}
