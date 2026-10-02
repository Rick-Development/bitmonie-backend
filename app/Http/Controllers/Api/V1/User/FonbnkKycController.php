<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Services\FonbnkKycService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class FonbnkKycController extends Controller
{
    public function __construct(
        protected FonbnkKycService $kyc
    ) {
    }

    /**
     * Get user's Fonbnk KYC state
     *
     * GET /api/v1/fonbnk/kyc/status
     */
    public function status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => [
                'required',
                'string',
                'size:2',
                'alpha',
            ],
        ]);

        if ($validator->fails()) {
            return Response::errorResponse(
                $validator->errors()->first()
            );
        }

        try {
            $user = $request->user();

            $result = $this->kyc->status(
                $user,
                strtoupper($request->country)
            );

            if (!($result['ok'] ?? false)) {
                return Response::errorResponse(
                    $result['message'] ?? 'Failed to fetch Fonbnk KYC status.',
                    $result['data'] ?? null,
                    (int) ($result['http_status'] ?? 400)
                );
            }

            return Response::successResponse(
                $result['message'] ?? 'Fonbnk KYC status retrieved successfully.',
                $result['data'] ?? null
            );

        } catch (Throwable $e) {

            report($e);

            return Response::errorResponse(
                'Unable to retrieve Fonbnk KYC status.',
                null,
                500
            );
        }
    }


    /**
     * Submit user's Fonbnk KYC
     *
     * POST /api/v1/fonbnk/kyc/submit
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'country' => [
                'required',
                'string',
                'size:2',
                'alpha',
            ],

            'document_id' => [
                'required',
                'string',
                'max:255',
            ],

            'user_fields' => [
                'required',
                'array',
            ],

            'user_fields.first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'user_fields.last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'user_fields.dob' => [
                'required',
                'date_format:Y-m-d',
            ],

            'user_fields.images' => [
                'required',
                'array',
                'min:1',
            ],

            'user_fields.images.*.image_type_id' => [
                'required',
                'integer',
                'in:2,3,7',
            ],

            'user_fields.images.*.image' => [
                'required',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            return Response::errorResponse(
                $validator->errors()->first()
            );
        }

        /*
         * Fonbnk requires the complete request body,
         * including images, to be below 10 MB.
         */
        $requestSize = strlen($request->getContent());

        if ($requestSize >= 10 * 1024 * 1024) {
            return Response::errorResponse(
                'KYC request is too large. The combined request body and images must be less than 10MB.',
                null,
                413
            );
        }

        try {

            $user = $request->user();

            /*
             * Validate the image types.
             *
             * 2 = Selfie
             * 3 = Document front
             * 7 = Document back
             */
            $images = $request->input('user_fields.images', []);

            $imageTypes = collect($images)
                ->pluck('image_type_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();

            /*
             * Prevent duplicate image types.
             */
            if (count($imageTypes) !== count($images)) {
                return Response::errorResponse(
                    'Duplicate KYC image types are not allowed.'
                );
            }

            /*
             * Make sure the Base64 values actually look like Base64.
             */
            foreach ($images as $image) {

                $base64 = $image['image'] ?? null;

                if (!$base64) {
                    return Response::errorResponse(
                        'KYC image data is required.'
                    );
                }

                /*
                 * Support both:
                 *
                 * data:image/jpeg;base64,XXXX
                 *
                 * and:
                 *
                 * XXXX
                 *
                 * Fonbnk ultimately receives only the Base64 string.
                 */
                if (str_contains($base64, ',')) {
                    $base64 = explode(',', $base64, 2)[1];
                }

                if (base64_decode($base64, true) === false) {
                    return Response::errorResponse(
                        'One or more KYC images contain invalid Base64 data.'
                    );
                }
            }

            /*
             * Send only the data required by the service.
             */
            $payload = [
                'country' => strtoupper($request->country),
                'document_id' => $request->document_id,

                'user_fields' => [
                    'first_name' => $request->input(
                        'user_fields.first_name'
                    ),

                    'last_name' => $request->input(
                        'user_fields.last_name'
                    ),

                    'dob' => $request->input(
                        'user_fields.dob'
                    ),

                    'images' => $images,
                ],
            ];

            $result = $this->kyc->submit(
                $user,
                $payload
            );

            if (!($result['ok'] ?? false)) {
                return Response::errorResponse(
                    $result['message'] ?? 'Failed to submit Fonbnk KYC.',
                    $result['data'] ?? null,
                    (int) ($result['http_status'] ?? 400)
                );
            }

            return Response::successResponse(
                $result['message'] ?? 'Fonbnk KYC submitted successfully.',
                $result['data'] ?? null
            );

        } catch (Throwable $e) {

            report($e);

            return Response::errorResponse(
                'Unable to submit Fonbnk KYC.',
                null,
                500
            );
        }
    }


    /**
     * Get KYC requirements/tokens
     *
     * GET /api/v1/fonbnk/kyc/tokens
     */
    public function tokens(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => [
                'required',
                'string',
                'size:2',
                'alpha',
            ],
        ]);

        if ($validator->fails()) {
            return Response::errorResponse(
                $validator->errors()->first()
            );
        }

        try {

            $user = $request->user();

            $result = $this->kyc->tokens(
                $user,
                strtoupper($request->country)
            );

            if (!($result['ok'] ?? false)) {
                return Response::errorResponse(
                    $result['message'] ?? 'Failed to generate Fonbnk tokens.',
                    $result['data'] ?? null,
                    (int) ($result['http_status'] ?? 400)
                );
            }

            return Response::successResponse(
                $result['message'] ?? 'Fonbnk KYC tokens retrieved successfully.',
                $result['data'] ?? null
            );

        } catch (Throwable $e) {

            report($e);

            return Response::errorResponse(
                'Unable to retrieve Fonbnk KYC tokens.',
                null,
                500
            );
        }
    }
}