<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\PayscribeCustomersHelper;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\SafeHeavenIdentityCheckController;
use App\Http\Helpers\Response;

class PayscribeCustomerController extends Controller
{

    // protected $baseUrl = 'https://sandbox.payscribe.ng/api/v1/customers'; // Base URL for customer-related API
    protected $baseUrl; // Base URL for customer-related API
    // protected $apiKey = 'ps_pk_test_mjwKJDOh41Zrl5uMXUJqwy3pyPYx5d';
    protected $apiKey;

    public function __construct(private SafeHeavenIdentityCheckController $identityCheckController, private PayscribeCustomersHelper $payscribeCustomersHelper)
    {
        $this->apiKey = config('services.payscribe.secret'); // Store in .env
        //'ps_pk_test_Od2eDKnXWrVAAXat85kV4fQYjV0sAi';
        $this->baseUrl = config('services.payscribe.api_url') . '/customers'; // Store in .env

    }

    /**
     * Create a new customer (Tier 0) in the Payscribe ecosystem.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createCustomer(Request $request): JsonResponse
    {
        $validator = \Validator::make($request->all(), [
            'phone' => 'required',
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

        return response()->json($response, $response['status_code'] ?? 200);
    }

    public function getOtpTierTwo()
    {
        $data = [
            'type' => 'BVN',
            "number" => auth()->user()->identity_number,
        ];

        $response = $this->identityCheckController->initiateVerification($data);
        if ($response['statusCode'] === 200) {
            return Response::successResponse($response['message'], [
                "_id" => $response['data']["_id"],
                "type" => $response['data']["type"],
                "status" => $response['data']["status"],
                "debitAccountNumber" => $response['data']["debitAccountNumber"],
            ]);
        } else {
            return Response::errorResponse($response['message'] ?? 'Identity check failed', $response, $response['statusCode'] ?? 400);
        }
    }


    public function upgradeToTierOne(Request $request)
    {
        $request->validate([
            'otp' => 'required | string',
            'identityId' => 'required | string',
        ]);

        $verifyData = [
            'identityId' => $request['identityId'],
            "type" => 'BVN',
            "otp" => $request['otp'],
        ];

        $verify_res = $this->identityCheckController->validateVerification($verifyData);

        if ($verify_res['statusCode'] === 200) {
            $verified_data = $verify_res['data']['providerResponse'];


            $imagePath = asset('storage/app/public/uploads/AdihROvTAnyM1PFKWLLC0Gaqm3cRoW6YatXS9NG0.png');

            $data = [
                'customer_id' => auth()->user()->payscribe_id,
                'dob' => $verified_data['dateOfBirth'],
                'address' => [
                    'street' => 'Trans-Amadi Rd',
                    'city' => 'Port-Harcourt',
                    'state' => 'Rivers',
                    'country' => 'Nigeria',
                    'postal_code' => "500211",
                ],
                'identification_type' => 'BVN',
                'identification_number' => auth()->user()->identity_number,
                'photo' => $imagePath,
            ];
        } else {
            return $verify_res;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->apiKey",
        ])->post("{$this->baseUrl}/create/tier1", $data);
        if ($response['status'] === true) {
            $this->updateUserDetails($verified_data);
        }

        return $this->handleResponse($response);
    }

    private function updateUserDetails($data)
    {
        // $user = User::where('id', auth()->user()->id)->firstOrFail();

        auth()->user()->update([
            'firstname' => $data['firstName'],
            'lastname' => $data['lastName'],
            'middlename' => $data['middleName'] ?? null,
            'phone' => $data['phoneNumber1'],
            'gender' => $data['gender'],
            'tier' => 2,
            'dob' => $data['dateOfBirth'],
            'state_of_origin' => $data['stateOfOrigin'],
            'lga_of_origin' => $data['lgaOfOrigin'],
            'lga_of_residence' => $data['lgaOfResidence'],
        ]);
    }

    public function upgradeToTierTwo(Request $request)
    {
        $serverUrl = config('app.url');

        $request->validate([
            'type' => 'required | string',
            'number' => 'required | string',
            // 'image' => 'required | image'
        ]);

        // $image = $request->file('image');

        // $uploadedImg = $image->store('customerImg', 'public');
        // $imgUrl = Storage::url($uploadedImg);
        // $fullUrl = $serverUrl . $imgUrl;

        $imagePath = asset('storage/app/public/uploads/AdihROvTAnyM1PFKWLLC0Gaqm3cRoW6YatXS9NG0.png');

        $data = [
            'customer_id' => auth()->user()->payscribe_id,
            'identity' => [
                'type' => $request->type,
                'number' => $request->number,
                'country' => auth()->user()->country,
                'image' => $imagePath,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->apiKey",
        ])->post("{$this->baseUrl}/create/tier2", $data);

        if ($response['status'] === true) {
            auth()->user()->update([
                'tier' => 3
            ]);
        }

        return $this->handleResponse($response);
    }


    /**
     * Retrieve all customers with optional filtering.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllCustomers(Request $request): JsonResponse
    {
        $queryParams = [
            'page' => $request->get('page', 1),
            'page_size' => $request->get('page_size', 10),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'search' => $request->get('search'),
        ];

        // $response = $this->payscribeCustomersHelper->GetAllCustomer();

        $response = Http::withHeaders([
            'Authorization' => "Bearer ps_pk_test_5fJUELCWRxbYyqE0mylVlfeekNK9iY0990", // Replace with your actual API key
        ])->get("{$this->baseUrl}/");

        return response()->json($response);
    }

    /**
     * Get detailed information about a specific customer.
     *
     * @param string $customerId
     * @return JsonResponse
     */
    public function getCustomerDetails(string $customerId): JsonResponse
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->apiKey", // Replace with your actual API key
        ])->get("{$this->baseUrl}/{$customerId}/details");

        return $this->handleResponse($response);
    }

    /**
     * Whitelist or blacklist a customer based on their status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleCustomerBlacklist(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|string',
            'blacklist' => 'required|boolean',
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->apiKey", // Replace with your actual API key
        ])->post("{$this->baseUrl}/blacklist", $request->all());

        return $this->handleResponse($response);
    }

    /**
     * Update customer details in the Payscribe ecosystem.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateCustomer(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|string',
            'phone' => 'required|string',
            'dob' => 'required|date_format:Y-m-d',
            'address' => 'required|array',
            'identification_number' => 'required|string',
            'identification_type' => 'required|string',
            'photo' => 'required|string',
            'identity' => 'required|array',
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->apiKey", // Replace with your actual API key
        ])->patch("{$this->baseUrl}/update", $request->all());

        return $this->handleResponse($response);
    }

    /**
     * Retrieve all transactions for a specific customer.
     *
     * @param string $customerId
     * @return JsonResponse
     */
    public function getCustomerTransactions(string $customerId): JsonResponse
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer $this->apiKey", // Replace with your actual API key
        ])->get("{$this->baseUrl}/{$customerId}/transactions");

        return $this->handleResponse($response);
    }

    public function customerBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|string',
        ]);
        $customerBalance = User::where('payscribe_id', $data['customer_id'])->first()->account_balance;
        return Response::successResponse('Customer Balance', ['balance' => $customerBalance]);
    }

    public function customerTransactions(): JsonResponse
    {

        $transactions = Transaction::where('user_id', auth()->id())->paginate(10);
        return Response::successResponse('Customer Transactions', $transactions);
    }

    public function resetPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => 'required|string',
        ]);
        User::where('id', auth()->id())->update(['user_pin' => $data['pin']]);
        return Response::successResponse('Pin reset successful');
    }
    /**
     * Handle the response from the Payscribe API.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return JsonResponse
     */
    protected function handleResponse(\Illuminate\Http\Client\Response $response): JsonResponse
    {
        if ($response->successful()) {
            return response()->json($response->json(), $response->status());
        }

        return response()->json(['error' => $response->json()], $response->status());
    }
}