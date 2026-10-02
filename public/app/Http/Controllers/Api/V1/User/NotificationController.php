<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
         
class NotificationController extends Controller
{
    /**
     * Get User Notifications
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $notifications = auth()->user()->notifications()->orderBy('created_at', 'desc')->paginate(20);
        
        $data = [
            'notifications' => $notifications
        ];

        return Response::success(['Notifications fetched successfully'], $data);
    }
}
