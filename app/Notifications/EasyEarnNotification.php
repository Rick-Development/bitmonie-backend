<?php

namespace App\Notifications;

use App\Models\EasyEarnPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EasyEarnNotification extends Notification
{
    use Queueable;

    public function __construct(public EasyEarnPlan $plan, public string $event)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $titles = [
            'created' => 'EasyEarn plan created',
            'midpoint' => 'EasyEarn midpoint reminder',
            'matured' => 'EasyEarn plan matured',
            'withdrawn' => 'EasyEarn payout withdrawn',
            'cancelled' => 'EasyEarn plan cancelled',
        ];

        $messages = [
            'created' => "Your {$this->plan->usdt_amount_deposited} USDT EasyEarn plan is active.",
            'midpoint' => "Your EasyEarn plan is halfway to maturity.",
            'matured' => "Your EasyEarn plan has matured. Expected return: {$this->plan->expected_return} USDT.",
            'withdrawn' => "Your EasyEarn payout of {$this->plan->expected_return} USDT has been credited.",
            'cancelled' => "Your EasyEarn plan has been cancelled.",
        ];

        return [
            'title' => $titles[$this->event] ?? 'EasyEarn update',
            'message' => $messages[$this->event] ?? 'Your EasyEarn plan was updated.',
            'easyearn_plan_id' => $this->plan->id,
            'status' => $this->plan->status,
            'amount_deposited' => $this->plan->usdt_amount_deposited,
            'expected_return' => $this->plan->expected_return,
            'maturity_date' => optional($this->plan->maturity_date)->toDateTimeString(),
        ];
    }
}
