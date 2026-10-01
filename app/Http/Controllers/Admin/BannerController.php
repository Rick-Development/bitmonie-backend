<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Response;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\File;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $page_title = "Banner Management";
        $banners = Banner::latest()->paginate(20);
        return view('admin.sections.banner.index', compact('page_title', 'banners'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'link'  => 'nullable|string|max:255',
            'type'  => 'required|string|in:dashboard,home,p2p,gift_card',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'banner-add');
        }

        $validated = $validator->validated();

        $data = [
            'uuid'   => Str::uuid(),
            'link'   => $validated['link'] ?? null,
            'type'   => $validated['type'],
            'status' => true,
        ];

        if($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension();
            $path = 'banner-images';
            
            // Move file to public/banner-images
            $image->move(public_path($path), $imageName);
            
            $data['image'] = $path . '/' . $imageName;
        }

        try {
            Banner::create($data);
        } catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Banner created successfully!']]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target'        => 'required|exists:banners,uuid',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'link'          => 'nullable|string|max:255',
            'type'          => 'required|string|in:dashboard,home,p2p,gift_card',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'banner-edit');
        }

        $validated = $validator->validated();
        $banner = Banner::where('uuid', $validated['target'])->first();

        $data = [
            'link' => $validated['link'] ?? null,
            'type' => $validated['type'],
        ];

        if($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension();
            $path = 'banner-images';

            // Delete old image if exists
            if($banner->image && File::exists(public_path($banner->image))) {
                File::delete(public_path($banner->image));
            }

            $image->move(public_path($path), $imageName);
            $data['image'] = $path . '/' . $imageName;
        }

        try {
            $banner->update($data);
        } catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Banner updated successfully!']]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'target' => 'required|exists:banners,uuid',
        ]);

        $banner = Banner::where('uuid', $request->target)->first();

        try {
            if($banner->image && File::exists(public_path($banner->image))) {
                File::delete(public_path($banner->image));
            }
            $banner->delete();
        } catch(Exception $e) {
            return back()->with(['error' => ['Something went wrong! Please try again.']]);
        }

        return back()->with(['success' => ['Banner deleted successfully!']]);
    }

    public function statusUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'status'                    => 'required|boolean',
            'data_target'               => 'required|string',
        ]);

        if ($validator->stopOnFirstFailure()->fails()) {
            return Response::error($validator->errors()->all());
        }

        $validated = $validator->validated();
        $banner_id = $validated['data_target'];

        $banner = Banner::where('uuid', $banner_id)->first();
        if(!$banner) {
            return Response::error(['Banner record not found!'], [], 404);
        }

        try {
            $banner->update([
                'status' => ($validated['status'] == true) ? false : true,
            ]);
        } catch(Exception $e) {
            return Response::error(['Something went wrong! Please try again.']);
        }

        return Response::success(['Banner status updated successfully!'], [], 200);
    }
}