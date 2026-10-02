<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SavingsPlan;
use Illuminate\Support\Str;

class SavingsPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "SafeLock Plans Configuration";
        $plans = SavingsPlan::orderBy('duration_days', 'asc')->get();
        return view('admin.sections.savings-plans.index', compact('page_title', 'plans'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:savings_plans,name',
            'duration_days' => 'required|integer|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
        ]);

        SavingsPlan::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'duration_days' => $request->duration_days,
            'interest_rate' => $request->interest_rate,
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
            'status' => true,
        ]);

        return back()->with(['success' => ['SafeLock Plan created successfully.']]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:savings_plans,name,'.$id,
            'duration_days' => 'required|integer|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
        ]);

        $plan = SavingsPlan::findOrFail($id);
        $plan->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'duration_days' => $request->duration_days,
            'interest_rate' => $request->interest_rate,
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
        ]);

        return back()->with(['success' => ['SafeLock Plan updated successfully.']]);
    }

    /**
     * Toggle status.
     */
    public function statusUpdate(Request $request)
    {
        $request->validate([
            'data_target' => 'required|numeric|exists:savings_plans,id',
        ]);

        $plan = SavingsPlan::findOrFail($request->data_target);
        $plan->status = !$plan->status;
        $plan->save();

        return response()->json(['success' => ['Status updated successfully.']]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $plan = SavingsPlan::findOrFail($id);
        $plan->delete();

        return back()->with(['success' => ['SafeLock Plan deleted successfully.']]);
    }
}
