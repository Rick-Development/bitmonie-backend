<?php

namespace App\Notifications;

use App\Models\AutosaveTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AutosaveDeductionNotification extends Notification
{
    use Queueable;

    public function __construct(public AutosaveTransaction $transaction)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->transaction->status === 'successful'
                ? 'AutoSave deduction successful'
                : 'AutoSave deduction failed',
            'message' => $this->transaction->status === 'successful'
                ? "AutoSave moved {$this->transaction->amount} to your plan."
                : ($this->transaction->failure_reason ?: 'AutoSave deduction could not be completed.'),
            'autosave_plan_id' => $this->transaction->autosave_plan_id,
            'autosave_transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->amount,
            'status' => $this->transaction->status,
            'type' => $this->transaction->type,
            'reference' => $this->transaction->reference,
        ];
    }
}
