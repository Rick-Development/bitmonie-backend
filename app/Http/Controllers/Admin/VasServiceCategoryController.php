<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VasService;
use App\Models\VasServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class VasServiceCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "VAS Service Categories";
        $categories = VasServiceCategory::with('service')->latest()->paginate(20);
        $services = VasService::all();
        return view('admin.vas-services-categories.index', compact('page_title', 'categories', 'services'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vas_service_id' => 'required|exists:vas_services,id',
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:vas_service_categories,identifier,NULL,id,vas_service_id,'.$request->vas_service_id,
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'add-category');
        }

        try {
            VasServiceCategory::create([
                'vas_service_id' => $request->vas_service_id,
                'name' => $request->name,
                'identifier' => $request->identifier,
                'status' => true,
            ]);
            return back()->with(['success' => ['Category Added Successfully!']]);
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
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
        $category = VasServiceCategory::find($id);
        if(!$category) return back()->with(['error' => ['Category not found!']]);

        $validator = Validator::make($request->all(), [
            'vas_service_id' => 'required|exists:vas_services,id',
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|unique:vas_service_categories,identifier,'.$id.',id,vas_service_id,'.$request->vas_service_id,
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'edit-category');
        }

        try {
            $category->update([
                'vas_service_id' => $request->vas_service_id,
                'name' => $request->name,
                'identifier' => $request->identifier,
            ]);
            return back()->with(['success' => ['Category Updated Successfully!']]);
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
    }

    public function status($id)
    {
        $category = VasServiceCategory::find($id);
        if(!$category) return back()->with(['error' => ['Category not found!']]);

        try {
            $category->update([
                'status' => !$category->status,
            ]);
            return back()->with(['success' => ['Status Updated Successfully!']]);
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $category = VasServiceCategory::find($id);
        if(!$category) return back()->with(['error' => ['Category not found!']]);

        try {
            $category->delete();
            return back()->with(['success' => ['Category Deleted Successfully!']]);
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }
    }
}
