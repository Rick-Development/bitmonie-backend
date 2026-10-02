<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2POrder;
use App\Models\P2PFeedback;
use App\Models\P2PUserStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class P2PFeedbackController extends Controller
{
    /**
     * Submit feedback for an order
     */
    public function store(Request $request, $orderUid)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        return DB::transaction(function () use ($request, $orderUid) {
            $user = auth()->user();
            $order = P2POrder::findOrFail($orderUid);

            // 1. Validate participation
            if ($order->maker_id !== $user->id && $order->taker_id !== $user->id) {
                return Response::errorResponse('Unauthorized', null, 403);
            }

            // 2. Validate order status
            if ($order->status !== 'completed') {
                return Response::errorResponse('Feedback can only be left for completed orders');
            }

            // 3. Check duplicate feedback
            $existing = P2PFeedback::where('order_id', $order->id)
                ->where('from_user_id', $user->id)
                ->exists();

            if ($existing) {
                return Response::errorResponse('You have already submitted feedback for this order');
            }

            // 4. Determine target user
            $targetUserId = ($order->maker_id === $user->id) ? $order->taker_id : $order->maker_id;

            // 5. Create feedback
            $feedback = P2PFeedback::create([
                'order_id' => $order->id,
                'from_user_id' => $user->id,
                'to_user_id' => $targetUserId,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            // 6. Update target user stats
            $stats = P2PUserStat::firstOrCreate(['user_id' => $targetUserId]);
            
            // Calculate new average rating
            // Formula: ((current_rating * total_reviews) + new_rating) / (total_reviews + 1)
            // But better to query DB for accuracy
            
            $aggr = P2PFeedback::where('to_user_id', $targetUserId)
                ->selectRaw('COUNT(*) as count, AVG(rating) as avg_rating')
                ->first();

            $stats->rating = $aggr->avg_rating ?? 0;
            $stats->save();

            return Response::successResponse('Feedback submitted successfully', ['feedback' => $feedback]);
        });
    }

    /**
     * Get feedback for a user
     */
    public function index(Request $request, $userId = null)
    {
        $targetId = $userId ?? auth()->id();

        $feedbacks = P2PFeedback::where('to_user_id', $targetId)
            ->with(['fromUser:id,firstname,lastname,username', 'order:id,asset,fiat,amount,price'])
            ->latest()
            ->paginate(20);

        return Response::successResponse('Feedback fetched', ['feedbacks' => $feedbacks]);
    }
}
