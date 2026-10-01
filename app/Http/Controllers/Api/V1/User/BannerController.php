<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Helpers\Response;

class BannerController extends Controller
{
    /**
     * Get active banners.
     */
    public function index()
    {
        $banners = Banner::where('status', true)->latest()->get()->map(function($data){
            return [
                'type' => $data->type,
                'image' => asset($data->image),
                'link' => $data->link,
                'created_at' => $data->created_at,
            ];
        });

        return Response::successResponse('Banners fetched successfully', ['banners' => $banners]);
    }
}
