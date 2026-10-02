<?php

namespace App\Events;

use App\Models\P2PChat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class P2PMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(P2PChat $message)
    {
        $this->message = $message->load('sender:id,firstname,lastname,username');
    }

    public function broadcastOn()
    {
        return new PrivateChannel('p2p-order.' . $this->message->order_id);
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }
}
