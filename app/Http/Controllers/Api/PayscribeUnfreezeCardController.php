<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\UnfreezeCardHelper;
use App\Http\Helpers\Response;
use App\Models\PayscribeVirtualCardDetails;
use Illuminate\Http\Request;

class PayscribeUnfreezeCardController extends Controller
{
    public function __construct(private UnfreezeCardHelper $unfreezeCardHelper){}

    public function unfreeze(Request $request)
    {
        $request->validate([
            'card_id' => 'required|string',
        ]);

        $user = auth()->user();
        $card = PayscribeVirtualCardDetails::where('user_id', $user->id)
            ->where('card_id', $request->card_id)
            ->first();

        if (!$card) {
            return Response::errorResponse('Card not found or does not belong to you.', null, 404);
        }

        $response = json_decode($this->unfreezeCardHelper->unfreezeCard(['ref' => $request->card_id]), true);

        if (isset($response['status']) && $response['status'] === true) {
            $card->update(['card_status' => 'active']);
        }

        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card unfrozen successfully', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to unfreeze card', $response);
    }
}
