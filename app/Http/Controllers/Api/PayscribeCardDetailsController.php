<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\PayscribeCustomersHelper;
use App\Http\Helpers\Payscribe\CardIssusing\CardDetailsHelper;
use App\Http\Helpers\Response;

class PayscribeCardDetailsController extends Controller
{
    public function __construct(private CardDetailsHelper $cardDetailsHelper, private PayscribeCustomersHelper $payscribeCustomersHelper)
    {
    }
    public function createCard(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'customer_id' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'brand' => 'required|string',
            'amount' => 'required',
            'type' => 'sometimes',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors(), 422);
        }

        $data = $request->only([
            'customer_id',
            'currency',
            'brand',
            'amount',
            'type',
        ]);

        // if (is_null(auth()->user()->payscribe_customer_id)) {
        //     $this->createCustomer($data['phone']);
        // }

        // Overwrite customer_id with the authenticated user’s value
        $data['customer_id'] = auth()->user()->payscribe_customer_id;
        // dd($data['customer_id']);

        $referenceId = Str::uuid();
        $refIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $refIdString]);

        $response = json_decode($this->cardDetailsHelper->createCard($data), true);

        if (
            $response &&
            isset($response['status'], $response['description']) &&
            $response['status'] === false &&
            $response['description'] === 'Customer not found for this business.'
        ) {
            // do something
        }

        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card created successfully', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to create card', $response, $response['status_code'] ?? 400);
    }
    /**
     * GET /card-details/{card_id}
     * Fetch a single card's full details from Payscribe.
     */
    public function getCardDetails(Request $request, string $cardId)
    {
        $response = json_decode($this->cardDetailsHelper->getCardDetails($cardId), true);
        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card Details', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to fetch card details', $response);
    }

    /**
     * GET /cards
     * List all cards for the authenticated user from local DB.
     * Also returns the live Payscribe card list for the customer.
     */
    public function getUserCards(Request $request)
    {
        $user = auth()->user();

        // Local records
        $localCards = \App\Models\PayscribeVirtualCardDetails::where('user_id', $user->id)->get();

        // Live fetch from Payscribe
        $liveCards = [];
        if ($user->payscribe_customer_id) {
            $liveResponse = json_decode($this->cardDetailsHelper->getUserCards($user->payscribe_customer_id), true);
            $liveCards = $liveResponse ?? [];
        }

        return Response::successResponse('User Cards', [
            'local_cards' => $localCards,
            'payscribe_cards' => $liveCards,
        ]);
    }

    public function createCustomer($phone): JsonResponse
    {

        $user = auth()->user();

        $data = [
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'email' => $user->email,
            'phone' => $phone,
        ];

        $response = $this->payscribeCustomersHelper->createUser($data);
        // dd($response);
        if ($response && $response['status'] == true) {
            $user->payscribe_customer_id = $response['message']['details']['customer_id'];
            $user->payscribe_tier = $response['message']['details']['tier'];
            $user->payscribe_customer_phone = $response['message']['details']['phone'];
            $user->payscribe_customer_country = $response['message']['details']['country'];

            $user->save();

            return Response::successResponse('Customer created successfully', [
                'customer_id' => $user->payscribe_customer_id,
                'tier' => $user->payscribe_tier,
                'phone' => $user->payscribe_customer_phone,
                'payscribe_response' => $response,
            ], 201);
        }

        return Response::errorResponse($response['description'] ?? 'Failed to create customer', $response, $response['status_code'] ?? 400);
    }
}
