<?php

namespace App\Http\Controllers\Admin;

use App\Models\Interest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class InterestController extends Controller
{
    public function create_interest(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'interest_rate' => 'required|numeric|min:1',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => $validator->errors()
            ]);
        }

        // Create interest package
        $interest = Interest::create([
            'name' => $request->name ?? "Interest",
            'interest_rate' => $request->interest_rate,
            // 'duration_days' => $request->duration_days,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Interest package created successfully.',
            'data' => $interest
        ], 201);
    }
}
