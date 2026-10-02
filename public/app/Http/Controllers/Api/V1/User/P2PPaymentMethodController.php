<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class P2PPaymentMethodController extends Controller
{
    /**
     * List user's payment methods
     */
    public function index()
    {
        $methods = P2PPaymentMethod::where('user_id', auth()->id())
            ->where('status', 'active')
            ->get();

        return Response::successResponse('Payment methods fetched', ['payment_methods' => $methods]);
    }

    /**
     * Add new payment method
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'details' => 'required|array',
            'details.acc_no' => 'required|string',
            'details.acc_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $method = P2PPaymentMethod::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'provider' => $request->provider,
            'details' => $request->details,
            'status' => 'active',
        ]);

        return Response::successResponse('Payment method added', ['payment_method' => $method], 201);
    }

    /**
     * Show single payment method
     */
    public function show($id)
    {
        $method = P2PPaymentMethod::where('user_id', auth()->id())->findOrFail($id);

        return Response::successResponse('Payment method details', ['payment_method' => $method]);
    }

    /**
     * Update payment method
     */
    public function update(Request $request, $id)
    {
        $method = P2PPaymentMethod::where('user_id', auth()->id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'provider' => 'sometimes|string|max:255',
            'details' => 'sometimes|array',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $method->update($request->only(['name', 'provider', 'details', 'status']));

        return Response::successResponse('Payment method updated', ['payment_method' => $method]);
    }

    /**
     * Delete payment method
     */
    public function destroy($id)
    {
        $method = P2PPaymentMethod::where('user_id', auth()->id())->findOrFail($id);
        $method->delete();

        return Response::successResponse('Payment method deleted');
    }
}
