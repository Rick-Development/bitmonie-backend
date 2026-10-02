<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KycTierController extends Controller
{
    /**
     * Display a listing of the KYC tiers.
     */
    public function index()
    {
        $page_title = "KYC Tiers";
        $tiers = KycTier::orderBy('level', 'asc')->get();
        return view('admin.sections.kyc-tiers.index', compact('page_title', 'tiers'));
    }

    /**
     * Update the specified KYC tier.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target'      => 'required|integer|exists:kyc_tiers,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'requirements'=> 'nullable|string',
            'vform_id'    => 'nullable|string',
            'status'      => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with(['error' => $validator->errors()->all()]);
        }

        $validated = $validator->validated();
        $tier = KycTier::findOrFail($validated['target']);

        try {
            $tier->update([
                'name'         => $validated['name'],
                'description'  => $validated['description'],
                'requirements' => $validated['requirements'],
                'vform_id'     => $validated['vform_id'],
                'status'       => $validated['status'],
            ]);
        } catch (\Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['KYC Tier updated successfully!']]);
    }

    /**
     * Change status of the KYC tier.
     */
    public function statusUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data_target' => 'required|integer|exists:kyc_tiers,id',
            'status'      => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all()], 400);
        }

        $validated = $validator->validated();
        $tier = KycTier::findOrFail($validated['data_target']);

        try {
            $tier->update([
                'status' => !$validated['status'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => ['Something went wrong! Please try again.']], 500);
        }

        return response()->json(['success' => ['KYC Tier status updated successfully!']], 200);
    }
}
