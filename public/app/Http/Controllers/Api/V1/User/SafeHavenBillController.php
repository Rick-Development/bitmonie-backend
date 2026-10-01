<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Services\SafeHavenBillService;
use Exception;
use Illuminate\Http\Request;

class SafeHavenBillController extends Controller
{
    protected $billService;

    public function __construct(SafeHavenBillService $billService)
    {
        $this->billService = $billService;
    }

    public function getServices()
    {
        try {
            $data = $this->billService->getServices();

            return Response::successResponse('Services fetched successfully', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function getCategories($serviceId)
    {
        try {
            $data = $this->billService->getCategories($serviceId);

            return Response::successResponse('Service categories fetched successfully', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function getProducts($categoryId)
    {
        try {
            $data = $this->billService->getProducts($categoryId);

            return Response::successResponse('Products fetched successfully', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function verifyCustomer(Request $request)
    {
        $request->validate([
            'serviceCategoryId' => 'required|string',
            'entityNumber' => 'required|string',
        ]);

        try {
            $data = $this->billService->verifyCustomer($request->all());

            return Response::successResponse('Customer verified successfully', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function buyAirtime(Request $request)
    {
        $this->normalizePurchaseRequest($request, 'airtime');

        $request->validate([
            'amount' => 'required|numeric|min:50',
            'phoneNumber' => 'required|string',
            'network' => 'required|in:MTN,GLO,AIRTEL,9MOBILE',
        ]);

        try {
            $data = $this->billService->purchase(auth()->user(), 'airtime', $request->all());

            return Response::successResponse('Airtime purchase successful', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function buyData(Request $request)
    {
        $this->normalizePurchaseRequest($request, 'data');

        $request->validate([
            'amount' => 'required|numeric|min:50',
            'network' => 'required|in:MTN,GLO,AIRTEL,9MOBILE',
            'productId' => 'required|string',
            'phoneNumber' => 'required|string',
        ]);

        try {
            $data = $this->billService->purchase(auth()->user(), 'data', $request->all());

            return Response::successResponse('Data purchase successful', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function buyCable(Request $request)
    {
        $this->normalizePurchaseRequest($request, 'cable');

        $request->validate([
            'amount' => 'required|numeric|min:100',
            'provider' => 'required|in:DSTV,GOTV,STARTIMES',
            'productId' => 'required|string',
            'smartCardNumber' => 'required|string',
        ]);

        try {
            $data = $this->billService->purchase(auth()->user(), 'cable', $request->all());

            return Response::successResponse('Cable TV purchase successful', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function buyUtility(Request $request)
    {
        $this->normalizePurchaseRequest($request, 'utility');

        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'serviceCategoryId' => 'required_without:provider|string',
            'provider' => 'required_without:serviceCategoryId|string',
            'meterNumber' => 'required|string',
            'vendType' => 'required|in:PREPAID,POSTPAID',
        ]);

        try {
            $data = $this->billService->purchase(auth()->user(), 'utility', $request->all());

            return Response::successResponse('Utility bill purchase successful', $data);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    protected function normalizePurchaseRequest(Request $request, string $type): void
    {
        $payload = $request->all();

        if (isset($payload['network'])) {
            $payload['network'] = $this->normalizeCategoryName($payload['network'], [
                'ETISALAT' => '9MOBILE',
                '9 MOBILE' => '9MOBILE',
            ]);
        }

        if (isset($payload['provider'])) {
            $payload['provider'] = $this->normalizeCategoryName($payload['provider'], [
                'STARTIME' => 'STARTIMES',
                'PHEDC' => 'PHED',
                'PORT HARCOURT' => 'PHED',
                'PORTHARCOURT' => 'PHED',
                'ABUJA' => 'AEDC',
                'BENIN' => 'BEDC',
                'EKO' => 'EKEDC',
                'ENUGU' => 'EEDC',
                'IBADAN' => 'IBEDC',
                'IKEJA' => 'IKEDC',
                'JOS' => 'JEDC',
                'KADUNA' => 'KAEDC',
                'YOLA' => 'YEDC',
            ]);
        }

        if (empty($payload['productId']) && !empty($payload['bundleCode'])) {
            $payload['productId'] = $payload['bundleCode'];
        }

        if ($type === 'cable') {
            if (empty($payload['smartCardNumber']) && !empty($payload['cardNumber'])) {
                $payload['smartCardNumber'] = trim((string) $payload['cardNumber']);
            }

            if (empty($payload['entityNumber']) && !empty($payload['smartCardNumber'])) {
                $payload['entityNumber'] = $payload['smartCardNumber'];
            }
        }

        if ($type === 'utility') {
            if (empty($payload['meterNumber']) && !empty($payload['entityNumber'])) {
                $payload['meterNumber'] = trim((string) $payload['entityNumber']);
            }

            if (empty($payload['entityNumber']) && !empty($payload['meterNumber'])) {
                $payload['entityNumber'] = trim((string) $payload['meterNumber']);
            }

            if (isset($payload['vendType'])) {
                $payload['vendType'] = strtoupper(trim((string) $payload['vendType']));
            }
        }

        $request->merge($payload);
    }

    protected function normalizeCategoryName(string $value, array $aliases = []): string
    {
        $normalized = strtoupper(trim($value));

        return $aliases[$normalized] ?? $normalized;
    }
}
