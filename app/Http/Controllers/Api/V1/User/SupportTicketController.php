<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserSupportTicket;
use App\Models\UserSupportChat;
use App\Models\UserSupportTicketAttachment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use App\Notifications\User\SupportTicketNotification;
use App\Events\Admin\SupportConversationEvent;
use App\Providers\Admin\BasicSettingsProvider;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $support_tickets = UserSupportTicket::authTickets()->orderByDesc("id")->paginate(20);
        return Response::successResponse('Support tickets fetched successfully!', ['support_tickets' => $support_tickets]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject'           => "required|string|max:255",
            'desc'              => "required|string|max:5000",
            'attachment.*'      => "nullable|file|max:204800",
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first(), []);
        }

        $validated = $validator->validate();
        $user = Auth::user();

        $data = [
            'name'       => $user->fullname,
            'email'      => $user->email,
            'token'      => generate_unique_string('user_support_tickets', 'token'),
            'user_id'    => $user->id,
            'status'     => 3, // Pending
            'subject'    => $validated['subject'],
            'desc'       => $validated['desc'],
        ];

        try {
            $support_ticket = UserSupportTicket::create($data);
            $support_ticket_id = $support_ticket->id;

            // Optional: Email Notification
            $basic_settings = BasicSettingsProvider::get();
            if ($basic_settings->email_notification == true) {
                 Notification::route('mail', $user->email)->notify(new SupportTicketNotification($data));
            }

        } catch (\Exception $e) {
            return Response::errorResponse('Something went wrong! Please try again.', []);
        }

        if ($request->hasFile('attachment')) {
            $validated_files = $request->file("attachment");
            $attachment = [];
            foreach ($validated_files as $item) {
                $upload_file = upload_file($item, 'support-attachment');
                if ($upload_file != false) {
                    $attachment[] = [
                        'user_support_ticket_id' => $support_ticket_id,
                        'attachment'             => $upload_file['name'],
                        'attachment_info'        => json_encode($upload_file),
                        'created_at'             => now(),
                    ];
                }
            }
            try {
                UserSupportTicketAttachment::insert($attachment);
            } catch (\Exception $e) {
                // Ignore attachment failure or handle it
            }
        }

        return Response::successResponse('Support ticket created successfully!', ['ticket' => $support_ticket]);
    }

    /**
     * Get conversation details
     */
    public function conversation($token)
    {
        $support_ticket = UserSupportTicket::where('token', $token)->authTickets()->with(['conversations', 'attachments'])->first();
        if (!$support_ticket) {
            return Response::errorResponse('Support ticket not found or not authorized!', [], 404);
        }
        return Response::successResponse('Conversation fetched successfully!', ['support_ticket' => $support_ticket]);
    }

    /**
     * Send a message in the conversation
     */
    public function messageSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message'       => 'required|string|max:200',
            'support_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first(), []);
        }
        $validated = $validator->validate();

        $support_ticket = UserSupportTicket::notSolved($validated['support_token'])->authTickets()->first();
        if (!$support_ticket) {
            return Response::errorResponse('This support ticket is closed or not found.');
        }

        $data = [
            'user_support_ticket_id' => $support_ticket->id,
            'sender'                 => auth()->user()->id,
            'sender_type'            => "USER",
            'message'                => $validated['message'],
            'receiver_type'          => "ADMIN",
        ];

        try {
            $chat_data = UserSupportChat::create($data);
        } catch (\Exception $e) {
            return Response::errorResponse('Message sending failed! Please try again.');
        }

        // Try to broadcast event, but don't fail if it doesn't work
        try {
            event(new SupportConversationEvent($support_ticket, $chat_data));
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            //\Log::warning('Support ticket event broadcasting failed: ' . $e->getMessage());
        }

        return Response::successResponse('Message sent successfully!', ['message' => $chat_data]);
    }
}
