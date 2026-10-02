<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Exceptions\YellowCardApiException;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Services\YellowCard\YellowCardCoverageService;
use Illuminate\Http\Request;

class YellowCardController extends Controller
{
    public function coverage(Request $request, YellowCardCoverageService $coverageService)
    {
        $filters = $request->only(['country', 'currency', 'channel_type', 'ramp_type', 'status']);

        return Response::successResponse('Yellow Card coverage fetched successfully', $coverageService->coverage($filters));
    }

    public function syncCoverage(Request $request, YellowCardCoverageService $coverageService)
    {
        $request->validate([
            'country' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $result = $coverageService->refresh($request->input('country'));
        } catch (YellowCardApiException $exception) {
            $status = $exception->statusCode();

            return Response::errorResponse($exception->getMessage(), [
                'status_code' => $exception->statusCode(),
                'provider_response' => $exception->response(),
            ], $status >= 400 && $status <= 599 ? $status : 500);
        }

        return Response::successResponse('Yellow Card coverage synced successfully', $result);
    }
}
