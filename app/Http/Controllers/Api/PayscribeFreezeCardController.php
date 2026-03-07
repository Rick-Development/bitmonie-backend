<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\FreezeCardHelper;
use App\Http\Helpers\Response;
use App\Models\PayscribeVirtualCardDetails;
use Illuminate\Http\Request;

class PayscribeFreezeCardController extends Controller
{
    public function __construct(private FreezeCardHelper $freezeCardHelper){}

    public function freeze(Request $request)
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

        $response = json_decode($this->freezeCardHelper->freezeCard(['ref' => $request->card_id]), true);

        if (isset($response['status']) && $response['status'] === true) {
            $card->update(['card_status' => 'frozen']);
        }

        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card frozen successfully', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to freeze card', $response);
    }
}
