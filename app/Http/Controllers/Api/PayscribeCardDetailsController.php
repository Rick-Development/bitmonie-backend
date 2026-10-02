<?php
namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\PayscribeCustomersHelper;
use App\Http\Helpers\Payscribe\CardIssusing\CardDetailsHelper;
use App\Http\Helpers\Response;
use App\Traits\Notify; // <-- 1. Import Notify trait
use Illuminate\Support\Facades\Validator;
class PayscribeCardDetailsController extends Controller
{
    use Notify; // <-- 2. Use Notify trait

    public function __construct(
        private CardDetailsHelper $cardDetailsHelper, 
        private PayscribeCustomersHelper $payscribeCustomersHelper
    ) {
    }

    public function createCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'brand' => 'required|string',
            'amount' => 'required|numeric',
            'type' => 'sometimes',
            'phone' => 'sometimes|string', // Added phone validation for auto-creation fallback
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = auth()->user();

        // Auto-create customer if missing on Payscribe
        if (is_null($user->payscribe_customer_id)) {
            $phone = $request->input('phone') ?? $user->phone ?? '08000000000';
            $customerRes = json_decode($this->createCustomerInternal($phone), true);
            if (!$customerRes['status']) {
                return Response::errorResponse('Failed to initialize customer profile for card creation.', $customerRes, 400);
            }
        }

        $data = $request->only([
            'currency',
            'brand',
            'amount',
            'type',
        ]);

        $data['customer_id'] = $user->payscribe_customer_id;

        $referenceId = Str::uuid();
        $refIdString = (string) $referenceId . '-auto_card';
        $data = array_merge($data, ['ref' => $refIdString]);

        $response = json_decode($this->cardDetailsHelper->createCard($data), true);

        // Handle error where customer is missing on gateway side
        if (
            $response &&
            isset($response['status'], $response['description']) &&
            $response['status'] === false &&
            $response['description'] === 'Customer not found for this business.'
        ) {
            $phone = $user->phone ?? '08000000000';
            $this->createCustomerInternal($phone);
            // Retry card creation once after fixing customer profile
            $data['customer_id'] = $user->fresh()->payscribe_customer_id;
            $response = json_decode($this->cardDetailsHelper->createCard($data), true);
        }

        if (isset($response['status']) && $response['status'] === true) {
            // Optional: Save to local database table if applicable
            // \App\Models\PayscribeVirtualCardDetails::create([...]);

            // Notify User via your custom Notify trait
            $this->sendNotification(
                user: $user,
                templateKey: 'VIRTUAL_CARD_CREATED', // Ensure template exists in DB
                params: [
                    'user' => $user->firstname,
                    'brand' => $data['brand'],
                    'amount' => number_format($data['amount']),
                    'reference' => $response['message']['details']['ref'] ?? $refIdString,
                    'status' => 'Successful',
                ],
                channels: ['mail', 'inapp'],
                options: [
                    'referenceId' => $refIdString,
                ]
            );

            return Response::successResponse('Card created successfully', $response);
        }

        return Response::errorResponse($response['description'] ?? 'Failed to create card', $response, $response['status_code'] ?? 400);
    }

    private function createCustomerInternal($phone)
    {
        $user = auth()->user();
        $data = [
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'email' => $user->email,
            'phone' => $phone,
        ];

        $response = $this->payscribeCustomersHelper->createUser($data);
        if ($response && $response['status'] == true) {
            $user->payscribe_customer_id = $response['message']['details']['customer_id'];
            $user->payscribe_tier = $response['message']['details']['tier'];
            $user->payscribe_customer_phone = $response['message']['details']['phone'];
            $user->payscribe_customer_country = $response['message']['details']['country'];
            $user->save();
            return json_encode(['status' => true]);
        }
        return json_encode(['status' => false, 'response' => $response]);
    }

    public function getCardDetails(Request $request, string $cardId)
    {
        $response = json_decode($this->cardDetailsHelper->getCardDetails($cardId), true);
        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card Details', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to fetch card details', $response);
    }

    public function getUserCards(Request $request)
    {
        $user = auth()->user();
        $localCards = \App\Models\PayscribeVirtualCardDetails::where('user_id', $user->id)->get();

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

    public function createCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = auth()->user();
        $data = [
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'email' => $user->email,
            'phone' => $request->phone,
        ];

        $response = $this->payscribeCustomersHelper->createUser($data);
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