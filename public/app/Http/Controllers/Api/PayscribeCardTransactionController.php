<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\CardTransactionHelper;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class PayscribeCardTransactionController extends Controller
{
    private $currentDate;

    public function __construct(private CardTransactionHelper $cardTransactionHelper){
        $this->currentDate = date('Y-m-d'); // Current date

    }
    public function createCardTransaction(Request $request){
        $data = $request->validate(
            [
                'ref' => 'required | string',
                'start_date' => 'sometimes | string',
                'end_date' => 'sometimes | string',
                'page' => 'sometimes | string',
            ]
        );
        // list($ref, $start_date, $end_date, $page) = $data;
        // ['ref' => $ref, 'start_date' => $start_data, 'end_date' => $end_data, 'page' => $page] = $data;
        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId;
        $data = array_merge($data, ['ref' => $referenceIdString]);
        
        $ref = $data['ref'];
        $start_data = $data['start_data'] ?? $this->currentDate;
        $end_data = $data['end_data'] ?? $this->currentDate;
        $page = $data['page'] ?? 1;

        $response = json_decode($this->cardTransactionHelper->cardTransaction($ref, $start_data, $end_data, $page), true);
        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card Transactions', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to fetch card transactions', $response);
    }

    /**
     * GET /card-transactions/{card_id}
     * Fetch transaction history for a specific card from Payscribe.
     */
    public function customerTransactions(Request $request, string $cardId)
    {
        $startDate = $request->query('start_date', $this->currentDate);
        $endDate   = $request->query('end_date', $this->currentDate);
        $page      = $request->query('page', 1);

        $response = json_decode($this->cardTransactionHelper->cardTransaction($cardId, $startDate, $endDate, $page), true);
        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card Transactions', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to fetch card transactions', $response);
    }
}