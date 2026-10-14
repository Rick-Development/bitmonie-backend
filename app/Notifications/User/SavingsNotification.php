<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SavingsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $planType;
    public string $action;
    public mixed $amount;
    public string $currency;
    public ?string $reference;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        string $planType,
        string $action,
        mixed $amount,
        string $currency = 'NGN',
        ?string $reference = null
    ) {
        $this->planType  = $planType;
        $this->action    = $action;
        $this->amount    = $amount;
        $this->currency  = strtoupper($currency);
        $this->reference = $reference;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $name = $notifiable->firstname ?? $notifiable->fullname ?? 'Valued Customer';
        $formattedAction = match (strtolower($this->action)) {
            'topup'     => 'Top-Up',
            'created'   => 'Deposit / Plan Creation',
            'withdrawn' => 'Withdrawal',
            'broken'    => 'Early Liquidation',
            'matured'   => 'Maturity Payout',
            default     => ucfirst($this->action),
        };

        $decimals = ($this->currency === 'USDT') ? 8 : 2;
        $formattedAmount = number_format((float) $this->amount, $decimals, '.', ',');
        $currencySymbol = ($this->currency === 'USDT') ? 'USDT' : '₦';
        $displayAmount = "{$currencySymbol} {$formattedAmount}";
        $timestamp = Carbon::now()->format('d M Y, h:i A');

        if (strtolower($this->action) === 'failed') {
            return (new MailMessage)
                ->subject("Bitmonie - {$this->planType} Scheduled Contribution Failed")
                ->greeting("Hello {$name},")
                ->line("Your scheduled **{$this->planType}** contribution of **{$displayAmount}** could not be collected on {$timestamp}.")
                ->line('No money was taken from your wallet and no fee was charged. Please make sure your wallet is funded; your next scheduled contribution will run as planned.');
        }

        return (new MailMessage)
            ->subject("Bitmonie - {$this->planType} {$formattedAction} Confirmation")
            ->greeting("Hello {$name},")
            ->line("This is to confirm that a **{$formattedAction}** on your **{$this->planType}** savings plan has been processed successfully.")
            ->line("**Transaction Details:**")
            ->line("• **Savings Plan:** {$this->planType}")
            ->line("• **Transaction Type:** {$formattedAction}")
            ->line("• **Amount:** {$displayAmount}")
            ->line("• **Date & Time:** {$timestamp}")
            ->line("• **Status:** Successful")
            ->line("If you have any questions or did not authorize this transaction, please contact Bitmonie support immediately.")
            ->line('Thank you for growing your savings with Bitmonie!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toDatabase($notifiable): array
    {
        $decimals = ($this->currency === 'USDT') ? 8 : 2;
        $formattedAmount = number_format((float) $this->amount, $decimals, '.', ',');

        if (strtolower($this->action) === 'failed') {
            return [
                'title'     => 'Bitmonie Savings Contribution Failed',
                'message'   => "Your scheduled {$this->planType} contribution of {$formattedAmount} {$this->currency} could not be collected. No fee was charged.",
                'amount'    => $this->amount,
                'currency'  => $this->currency,
                'plan_type' => $this->planType,
                'action'    => $this->action,
                'reference' => $this->reference,
                'type'      => 'SAVINGS_FAILED',
            ];
        }

        return [
            'title'     => "Bitmonie Savings " . ucfirst($this->action),
            'message'   => "Your {$this->planType} savings {$this->action} of {$formattedAmount} {$this->currency} was successful.",
            'amount'    => $this->amount,
            'currency'  => $this->currency,
            'plan_type' => $this->planType,
            'action'    => $this->action,
            'reference' => $this->reference,
            'type'      => 'SAVINGS_' . strtoupper($this->action),
        ];
    }
}
