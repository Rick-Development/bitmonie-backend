<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PDisclaimer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class P2PDisclaimerController extends Controller
{
    /**
     * List all disclaimers
     */
    public function index()
    {
        $page_title = "P2P Disclaimers";
        $disclaimers = P2PDisclaimer::latest()->get();

        return view('admin.sections.p2p.disclaimer.index', compact('page_title', 'disclaimers'));
    }

    /**
     * Create disclaimer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:p2p_disclaimers,key',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:info,warning,critical',
            'requires_acceptance' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $disclaimer = P2PDisclaimer::create($request->all());

        return back()->with(['success' => ['Disclaimer created successfully']]);
    }

    /**
     * Show disclaimer
     */
    public function show($id)
    {
        // Typically handled by modal in index, or separate view
        $disclaimer = P2PDisclaimer::with('acceptances')->findOrFail($id);
        return back()->with(['info' => ['Disclaimer details loaded']]); 
    }

    /**
     * Update disclaimer
     */
    public function update(Request $request, $id)
    {
        $disclaimer = P2PDisclaimer::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'key' => 'sometimes|string|unique:p2p_disclaimers,key,' . $id,
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => 'sometimes|in:info,warning,critical',
            'requires_acceptance' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
             return back()->withErrors($validator)->withInput();
        }

        $disclaimer->update($request->all());

        return back()->with(['success' => ['Disclaimer updated successfully']]);
    }

    /**
     * Delete disclaimer
     */
    public function destroy($id)
    {
        $disclaimer = P2PDisclaimer::findOrFail($id);
        $disclaimer->delete();

        return back()->with(['success' => ['Disclaimer deleted successfully']]);
    }
}
