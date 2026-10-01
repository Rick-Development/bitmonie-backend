<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Traits\Notify;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Notify;

    protected $user;
    protected string $templateKey;
    protected array $params;
    protected array $channels;
    protected array $options;

    public function __construct($user, string $templateKey, array $params = [], array $channels = ['mail'], array $options = [])
    {
        $this->user = $user;
        $this->templateKey = $templateKey;
        $this->params = $params;
        $this->channels = $channels;
        $this->options = $options;
    }

    public function handle(): void
    {
        $subject        = $this->options['subject'] ?? null;
        $requestMessage = $this->options['requestMessage'] ?? null;
        $action         = $this->options['action'] ?? [];
        $referenceId    = $this->options['referenceId'] ?? null;
        $isVerification = $this->options['isVerification'] ?? false;

        foreach (array_unique($this->channels) as $channel) {
            try {
                $status = match ($channel) {
                    'mail' => $this->sendEmailNotification(
                        user: $this->user,
                        templateKey: $this->templateKey,
                        params: $this->params,
                        subject: $subject,
                        requestMessage: $requestMessage,
                        isVerification: $isVerification,
                        notifyFor: 0,
                        referenceId: $referenceId
                    ),

                    'sms' => $isVerification
                        ? $this->verifyToSms($this->user, $this->templateKey, $this->params, $requestMessage, $referenceId)
                        : $this->sms($this->user, $this->templateKey, $this->params, $requestMessage, $referenceId),

                    'push' => $this->userFirebasePushNotification(
                        $this->user,
                        $this->templateKey,
                        $this->params,
                        $action,
                        $referenceId
                    ),

                    'inapp' => $this->userPushNotification(
                        user: $this->user,
                        templateKey: $this->templateKey,
                        params: $this->params,
                        action: $action,
                        referenceType: $this->options['referenceType'] ?? null,
                        referenceId: $referenceId,
                        type: $this->options['type'] ?? 'general',
                        priority: $this->options['priority'] ?? 'normal'
                    ),

                    default => false,
                };

                Log::info("Notification channel [{$channel}] processed successfully.");

            } catch (Throwable $e) {
                $this->logNotificationError(
                    "SendNotificationJob.{$channel}",
                    $this->user,
                    $this->templateKey,
                    $e
                );
            }
        }
    }
}