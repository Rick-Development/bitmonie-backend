<?php

namespace App\Traits;

use App\Events\AdminNotification;
use Illuminate\Http\Request;
use App\Events\UserNotification;
use App\Jobs\SendAdminEmailJob;
use App\Jobs\SendAdminPushJob;
use App\Jobs\SendNotificationJob;
use App\Mail\UserEmail;
use App\Models\Admin;
use App\Models\FireBaseToken;
use App\Models\InAppNotification;
use App\Models\ManualSmsConfig;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\SMS\BaseSmsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Twilio\Rest\Client;
use Google\Client as GoogleClient;
use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Storage;


trait Notify
{
    /*
    |--------------------------------------------------------------------------
    | MULTI‑CHANNEL DISPATCHER
    |--------------------------------------------------------------------------
    */

    /**
     * Send a notification through multiple channels.
     *
     * @param mixed  $user           The user model (for user notifications) or null for admin
     * @param string $templateKey    The notification template key
     * @param array  $params         Parameters to replace placeholders
     * @param array  $channels       List of channels: 'mail', 'sms', 'push', 'inapp'
     * @param array  $options        Additional options: subject, requestMessage, action, referenceId
     * @return bool
     */
   public function sendNotification(
    $user,
    string $templateKey,
    array $params = [],
    array $channels = ['mail'],
    array $options = []
): bool {
    
    try {
        $this->queueNotification($user, $templateKey, $params, $channels, $options);
        return true;
    } catch (Throwable $e) {
        $this->logNotificationError(
            'sendNotification.queue',
            $user,
            $templateKey,
            $e
        );
        return false;
    }
}

    /**
     * Push the notification dispatch process to the queue.
     *
     * @param mixed  $user
     * @param string $templateKey
     * @param array  $params
     * @param array  $channels
     * @param array  $options
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function queueNotification(
        $user,
        string $templateKey,
        array $params = [],
        array $channels = ['mail'],
        array $options = []
    ) {
        return SendNotificationJob::dispatch(
            $user,
            $templateKey,
            $params,
            $channels,
            $options
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMBINED HELPERS (Legacy compatibility)
    |--------------------------------------------------------------------------
    */

    public function UserEmailSms($user, $templateKey, $params = [], $subject = null, $requestMessage = null): void
    {
        $this->queueNotification($user, $templateKey, $params, ['mail', 'sms'], [
            'subject'        => $subject,
            'requestMessage'  => $requestMessage,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE‑CHANNEL METHODS
    |--------------------------------------------------------------------------
    */

    public function mail($user, $templateKey = null, $params = [], $subject = null, $requestMessage = null, $referenceId = null): bool
    {
        return $this->sendEmailNotification(
            $user,
            $templateKey,
            $params,
            $subject,
            $requestMessage,
            notifyFor: 0,
            referenceId: $referenceId
        );
    }

    public function sms($user, $templateKey, $params = [], $requestMessage = null, $referenceId = null): bool
    {
        try {
            $basic = $this->getBasicConfig();

            if ($basic->sms_notification != 1) {
                return false;
            }

            if (!$this->hasNotificationPermission($user, $templateKey, 'template_sms_key')) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate($templateKey, $user->language_id, notifyFor: 0);

            if (!$templateObj && $requestMessage === null) {
                return false;
            }

            if (!$templateObj || empty($templateObj->status['sms'] ?? false)) {
                return false;
            }

            $message = $this->parseTemplate($templateObj->sms, $params);

            if (config('SMSConfig.default') === 'manual') {
                return $this->sendManualSms($user, $message);
            }

            BaseSmsService::sendSMS($user->phone_code . $user->phone, $message);
            return true;

        } catch (Throwable $e) {
            $this->logNotificationError('sms', $user, $templateKey, $e);
            return false;
        }
    }

    public function verifyToMail($user, $templateKey = null, $params = [], $subject = null, $requestMessage = null, $referenceId = null): bool
    {
        return $this->sendEmailNotification(
            $user,
            $templateKey,
            $params,
            $subject,
            $requestMessage,
            isVerification: true,
            referenceId: $referenceId
        );
    }

    public function verifyToSms($user, $templateKey, $params = [], $requestMessage = null, $referenceId = null): bool
    {
        try {
            $basic = $this->getBasicConfig();

            if ($basic->sms_verification != 1) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate($templateKey, $user->language_id);

            if (!$templateObj && $requestMessage === null) {
                return false;
            }

            $message = $templateObj
                ? $this->parseTemplate($templateObj->sms, $params)
                : $this->parseTemplate($requestMessage, $params);

            if (config('SMSConfig.default') === 'manual') {
                return $this->sendManualSms($user, $message);
            }

            BaseSmsService::sendSMS($user->phone_code . $user->phone, $message);
            return true;

        } catch (Throwable $e) {
            $this->logNotificationError('verifyToSms', $user, $templateKey, $e);
            return false;
        }
    }

    public function sendWhatsAppMessage($otp): \Illuminate\Http\JsonResponse
    {
        $message = "{$otp} is your verification OTP. Don't share this code";

        $sid             = config('services.twilio.sid');
        $token           = config('services.twilio.token');
        $whatsappNumber  = config('services.twilio.whatsapp_number');

        $user            = auth()->user();
        $recipient       = 'whatsapp:' . $user->phone_code . $user->phone;

        try {
            $twilio = new Client($sid, $token);
            $twilio->messages->create($recipient, [
                'from' => $whatsappNumber,
                'body' => $message,
            ]);

            return response()->json($this->withSuccess('WhatsApp message sent successfully'));

        } catch (Throwable $e) {
            Log::error('Notify::sendWhatsAppMessage failed', ['error' => $e->getMessage()]);
            return response()->json($this->withError($e->getMessage()));
        }
    }

    public function userFirebasePushNotification($user, $templateKey, $params = [], $action = null, $referenceId = null): bool
    {
        return $this->sendFirebasePush(
            user: $user,
            templateKey: $templateKey,
            params: $params,
            action: $action,
            isAdmin: false,
            referenceId: $referenceId
        );
    }

    public function userPushNotification(
        $user,
        string $templateKey,
        array $params = [],
        array $action = [],
        ?string $referenceType = null,
        ?string $referenceId = null,
        string $type = 'general',
        string $priority = 'normal'
    ): bool {
        try {
            if (!$user) {
                return false;
            }

            $basic = $this->getBasicConfig();

            if ((int) $basic->in_app_notification !== 1) {
                return false;
            }

            if (!$this->hasNotificationPermission($user, $templateKey, 'template_in_app_key')) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate(
                $templateKey,
                $user->language_id,
                notifyFor: 0
            );

            if (!$templateObj || empty($templateObj->status['in_app'] ?? false)) {
                return false;
            }

            $message = $this->parseTemplate($templateObj->in_app, $params);
            $action['text'] = $message;

            $notification = $user->inAppNotifications()->create([
                'template_key'   => $templateKey,
                'type'           => $type,
                'title'          => $templateObj->name,
                'message'        => $message,
                'action'         => $action,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'priority'       => $priority,
            ]);

            event(new UserNotification(
                $user->id,
                $notification->toArray()
            ));

            return true;

        } catch (Throwable $e) {
            $this->logNotificationError(
                'userPushNotification',
                $user,
                $templateKey,
                $e
            );

            return false;
        }
    }

    public function adminFirebasePushNotification($templateKey, $params = [], $action = null, $referenceId = null): bool
    {
        return $this->sendFirebasePush(
            templateKey: $templateKey,
            params: $params,
            action: $action,
            isAdmin: true,
            referenceId: $referenceId
        );
    }

    public function adminPushNotification($templateKey, $params = [], $action = []): bool
    {
        try {
            $basic = $this->getBasicConfig();

            if ($basic->in_app_notification != 1) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate($templateKey, notifyFor: 1);

            if (!$templateObj || empty($templateObj->status['in_app'] ?? false)) {
                return false;
            }

            $message = $this->parseTemplate($templateObj->in_app, $params);
            $action['text'] = $message;

            Admin::query()->chunk(100, function ($admins) use ($action) {
                foreach ($admins as $admin) {
                    dispatch(new SendAdminPushJob($admin, $action));
                }
            });

            return true;

        } catch (Throwable $e) {
            $this->logNotificationError('adminPushNotification', null, $templateKey, $e);
            return false;
        }
    }

    public function adminMail($templateKey = null, $params = [], $subject = null, $requestMessage = null): bool
    {
        try {
            $basic = $this->getBasicConfig();

            if ($basic->email_notification != 1) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate($templateKey, notifyFor: 1);

            if (!$templateObj || empty($templateObj->status['mail'] ?? false)) {
                return false;
            }

            $emailBody = $basic->email_description;
            $message   = str_replace('[[message]]', $templateObj->email, $emailBody);
            $message   = empty($message) ? $emailBody : $message;
            $message   = $this->parseTemplate($message, $params);

            $subject    = $subject ?? $templateObj->subject;
            $emailFrom  = $basic->sender_email;

            Admin::query()->chunk(100, function ($admins) use ($emailFrom, $subject, $message) {
                foreach ($admins as $admin) {
                    dispatch(new SendAdminEmailJob($admin, $emailFrom, $subject, $message));
                }
            });

            return true;

        } catch (Throwable $e) {
            $this->logNotificationError('adminMail', null, $templateKey, $e);
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONVENIENCE WRAPPERS (Updated to queue by default)
    |--------------------------------------------------------------------------
    */

    public function loginNotify($user, array $channels = ['mail', 'push', 'inapp']): void
    {
        $params = ['user' => $user->firstname];
        $action = [
            'link' => '#',
            'icon' => 'fa fa-user text-white',
        ];
        $referenceId = $this->generateReferenceId('USER_LOGIN', $user->id);

        $this->queueNotification($user, 'USER_LOGIN', $params, $channels, [
            'action'      => $action,
            'referenceId' => $referenceId,
        ]);
    }

//     public function loginNotify(
//     $user,
//     Request $request,
//     array $channels = ['mail', 'push', 'inapp']
// ): void {

//     $dd = new DeviceDetector($request->userAgent());
//     $dd->parse();

//     $client = $dd->getClient();
//     $os     = $dd->getOs();

//     $device = trim(implode(' ', array_filter([
//         $dd->getBrandName(),
//         $dd->getModel(),
//     ])));

//     if ($device === '') {
//         $device = $dd->isDesktop()
//             ? 'Desktop'
//             : ($dd->getDeviceName() ?: 'Unknown Device');
//     }

//     $params = [
//         'user'       => $user->firstname,
//         'ip'         => $request->ip(),
//         'device'     => $device,
//         'deviceType' => $dd->getDeviceName() ?: 'Unknown',
//         'browser'    => $client['name'] ?? 'Unknown',
//         'browser_version' => $client['version'] ?? '',
//         'platform'   => $os['name'] ?? 'Unknown',
//         'platform_version' => $os['version'] ?? '',
//         'login_time' => now('Africa/Lagos')->format('d M Y, h:i:s A'),
//     ];

//     $action = [
//         'link' => '#',
//         'icon' => 'fa fa-user text-white',
//     ];

//     $referenceId = $this->generateReferenceId(
//         'USER_LOGIN',
//         $user->id
//     );

//     $this->queueNotification(
//         $user,
//         'USER_LOGIN',
//         $params,
//         $channels,
//         [
//             'action'      => $action,
//             'referenceId' => $referenceId,
//         ]
//     );
// }
// 
    public function sendWelcomeEmail($user, array $channels = ['mail', 'push', 'inapp']): void
    {
        $params = ['user' => $user->firstname];
        $referenceId = $this->generateReferenceId('WELCOME_NEW_USER', $user->id);

        $this->queueNotification($user, 'WELCOME_NEW_USER', $params, $channels, [
            'referenceId' => $referenceId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function getBasicConfig()
    {
        return Cache::remember('basic_config', 3600, function () {
            return basicControl();
        });
    }

    private function generateReferenceId(string $templateKey, $entityId = null): string
    {
        $parts = [$templateKey];
        if ($entityId !== null) {
            $parts[] = $entityId;
        }
        return implode('_', $parts);
    }

    private function sendEmailNotification(
        $user,
        ?string $templateKey = null,
        array $params = [],
        ?string $subject = null,
        ?string $requestMessage = null,
        bool $isVerification = false,
        int $notifyFor = 0,
        $referenceId = null
    ) {
        try {
            $basic = $this->getBasicConfig();

            if ($basic->email_notification != 1 && !$isVerification) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate(
                $templateKey,
                $user->language_id ?? null,
                $notifyFor
            );

            if (!$templateObj && ($subject === null || $requestMessage === null)) {
                return false;
            }

            $emailBody = $basic->email_description;
            $message   = str_replace('[[name]]', $user->firstname, $emailBody);
            
            if ($templateObj) {
                $message = str_replace('[[message]]', $templateObj->email, $message);
                $message = empty($message) ? $emailBody : $message;
                $message = $this->parseTemplate($message, $params);
            } else {
                $message = str_replace('[[message]]', $requestMessage, $message);
            }

            $finalSubject = $subject ?? ($templateObj->subject ?? '');
            $emailFrom    = $templateObj?->email_from ?? $basic->sender_email;

            Mail::to($user)->queue(new UserEmail($emailFrom, $finalSubject, $message));

            return true;

       } catch (Throwable $e) {
            $this->logNotificationError($isVerification ? 'verifyToMail' : 'mail', $user, $templateKey, $e);
            return false;
       }
    }

private function sendFirebasePush(
        $user = null,
        ?string $templateKey = null,
        array $params = [],
        $action = null,
        bool $isAdmin = false,
        $referenceId = null
    ): bool {
        try {
            $basic = $this->getBasicConfig();
            $notifyConfig = config('firebase');

            if (!$basic->push_notification || !$notifyConfig) {
                return false;
            }

            if (!$isAdmin && ($notifyConfig['user_foreground'] == 0 && $notifyConfig['user_background'] == 0)) {
                return false;
            }

            if (!$isAdmin && !$this->hasNotificationPermission($user, $templateKey, 'template_push_key')) {
                return false;
            }

            $templateObj = $this->getNotificationTemplate(
                $templateKey,
                $user?->language_id ?? null,
                $isAdmin ? 1 : 0
            );

            if (!$templateObj || empty($templateObj->status['push'] ?? false)) {
                return false;
            }

            $body  = $this->parseTemplate($templateObj->push, $params);
        
            $title = $isAdmin
                ? $templateObj->name
                : $templateObj->name . ' from ' . ($basic?->site_title ?: config('app.name', 'Bitmonie'));

            $modelType = $isAdmin ? Admin::class : ($user ? get_class($user) : null);
            $tokenQuery = FireBaseToken::where('tokenable_type', $modelType);
            
            if (!$isAdmin && $user) {
                $tokenQuery->where('tokenable_id', $user->id);
            }

            $tokens = $tokenQuery->get();

            if ($tokens->isEmpty()) {
                return true; 
            }

            $allSucceeded = true;

            foreach ($tokens as $tokenRecord) {
                if ($this->wasAlreadySent($tokenRecord, $templateKey, $user?->id, $referenceId)) {
                    continue;
                }

                $log = $this->createNotificationLog(
                    $tokenRecord,
                    $templateKey,
                    $title,
                    $body,
                    $user?->id,
                    $referenceId
                );

                try {
                    $projectId = config('services.fcm.project_id');
                    $accessToken = $this->getFirebaseAccessToken(); 
                    
                    $fcmPayload = [
                        'message' => [
                            'token' => $tokenRecord->token,
                            'notification' => [
                                'title' => $title,
                                'body'  => $body,
                                'image' => asset($basic->favicon),
                            ],
                            'data' => [
                                'foreground' => (string)(
                                    $isAdmin
                                        ? $notifyConfig['admin_foreground']
                                        : $notifyConfig['user_foreground']
                                ),
                                'background' => (string)(
                                    $isAdmin
                                        ? $notifyConfig['admin_background']
                                        : $notifyConfig['user_background']
                                ),
                                'click_action' => $action['link'] ?? '',
                                'reference_id' => (string) $referenceId,
                            ],
                            'android' => [
                                'priority' => 'high',
                                'notification' => [
                                    'sound' => 'default',
                                    'channel_id' => 'default',
                                ],
                            ],
                            'apns' => [
                                'payload' => [
                                    'aps' => [
                                        'sound' => 'default',
                                    ]
                                ]
                            ]
                        ]
                    ];

                    // // Log the exact payload being sent to Google Firebase
                    // Log::info('Google FCM Outbound Payload:', [
                    //     'url' => "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    //     'payload' => $fcmPayload,
                    //     'project_id'=>$projectId,
                    // ]);

                    $response = Http::withToken($accessToken)
                        ->acceptJson()
                        ->throw() // Throws RequestException on 4xx/5xx responses
                        ->post(
                            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                            $fcmPayload
                        );
                          Log::info('Google FCM Outbound Payload:', [
                        'url' => "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                        'payload' => $fcmPayload,
                        'response'=>$response,
                    ]);

                    $this->updateNotificationLog($log, $response);
                    
                } catch (RequestException $e) {
                    $allSucceeded = false;
                    $responseBody = $e->response ? $e->response->json() : null;

                    $log->update([
                        'status' => 'failed',
                        'error'  => $responseBody ?? $e->getMessage(),
                        'response' => $responseBody,
                    ]);

                 Log::error("Firebase push request failed for token: {$tokenRecord->token}", [
                        'project_id'=> $projectId,
                        'token'           => $tokenRecord->token,
                        'status_code'        => $e->response ? $e->response->status() : null,
                        'error_message'      => $e->getMessage(),
                        'fcm_response_body'  => $responseBody,
                        'admin_token_access' => $accessToken,
                        'fcm_payload'        => $fcmPayload,
                    ]);
                }
            }

            return $allSucceeded;

        } catch (Throwable $e) {
            $this->logNotificationError(
                $isAdmin ? 'adminFirebasePushNotification' : 'userFirebasePushNotification',
                $user,
                $templateKey,
                $e
            );
            return false;
        }
    }
    private function getFirebaseAccessToken(): string
    {
        $client = new GoogleClient();
        if (!Storage::disk('local')->exists('firebase/google-services.json')) {
            throw new \Exception("File not found via Storage facade.");
        }

        $jsonContent = Storage::disk('local')->get('firebase/google-services.json');
        $credentials = json_decode($jsonContent, true);

        $client->setAuthConfig($credentials);

        $client->addScope(
            'https://www.googleapis.com/auth/firebase.messaging'
        );

        $token = $client->fetchAccessTokenWithAssertion();

        if (!is_array($token) || !isset($token['access_token'])) {
            throw new \Exception("Failed to fetch Firebase access token. Response: " . json_encode($token));
        }

        return $token['access_token'];
    }

    private function getNotificationTemplate(
        ?string $templateKey,
        ?int $languageId = null,
        int $notifyFor = 0
    ): ?NotificationTemplate {
        if (!$templateKey) {
            return null;
        }

        $cacheKey = "notification_template_{$templateKey}_{$languageId}_{$notifyFor}";

        return Cache::remember($cacheKey, 86400, function () use ($templateKey, $languageId, $notifyFor) {
            $query = NotificationTemplate::where('template_key', $templateKey)
                ->where('notify_for', $notifyFor);

            if ($languageId) {
                $query->where('language_id', $languageId);
            }

            $template = $query->first();

            if (!$template && $languageId) {
                $template = NotificationTemplate::where('template_key', $templateKey)
                    ->where('notify_for', $notifyFor)
                    ->first();
            }

            return $template;
        });
    }

    private function hasNotificationPermission($user, ?string $templateKey, string $permissionField): bool
    {
        if (!$user || !$templateKey) {
            return false;
        }
        return true;
    }

    private function parseTemplate(string $template, array $params): string
    {
        foreach ($params as $code => $value) {
            $template = str_replace('[[' . $code . ']]', $value, $template);
        }
        return $template;
    }

    private function wasAlreadySent($tokenRecord, string $templateKey, ?int $userId = null, $referenceId = null): bool
    {
        $query = NotificationLog::where('firebase_token_id', $tokenRecord->id)
            ->where('template_key', $templateKey)
            ->where('status', 'sent');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($referenceId !== null) {
            $query->where('reference_id', $referenceId);
        } else {
            $query->where('sent_at', '>', now()->subMinutes(5));
        }

        return $query->exists();
    }

    private function createNotificationLog(
        $tokenRecord,
        string $templateKey,
        string $title,
        string $body,
        ?int $userId = null,
        $referenceId = null
    ): NotificationLog {
        return NotificationLog::create([
            'user_id'           => $userId ?? $tokenRecord->tokenable_id,
            'firebase_token_id' => $tokenRecord->id,
            'template_key'      => $templateKey,
            'reference_id'      => $referenceId,
            'title'             => $title,
            'body'              => $body,
            'status'            => 'pending',
            'sent_at'           => null,
        ]);
    }
   
    private function updateNotificationLog(NotificationLog $log, $response): void
    {
        $responseBody = $response->json();

        $log->update([
            'status'     => $response->successful() ? 'sent' : 'failed',
            'message_id' => $responseBody['name'] ?? null,
            'error'      => $response->successful() ? null : $responseBody,
            'response'   => $responseBody,
            'sent_at'    => $response->successful() ? now() : null,
        ]);
    }

    private function logNotificationError(string $method, $user, ?string $templateKey, Throwable $e): void
    {
        Log::error("Notify::{$method} failed", [
            'user'        => $user?->id ?? null,
            'templateKey' => $templateKey,
            'error'       => $e->getMessage(),
        ]);
    }

    private function sendManualSms($user, string $template): bool
    {
        try {
            $smsControl = ManualSmsConfig::firstOrCreate(['id' => 1]);

            $headerData = $smsControl->header_data ? json_decode($smsControl->header_data, true) : [];
            $formData   = $smsControl->form_data ? json_decode($smsControl->form_data, true) : [];

            $actionUrl    = $smsControl->action_url ?? '';
            $actionMethod = strtoupper($smsControl->action_method ?? 'POST');
            $contentType  = $headerData['Content-Type'] ?? '';

            array_walk_recursive($formData, function (&$value) use ($user, $template) {
                $value = str_replace(
                    ['[[receiver]]', '[[message]]'],
                    [$user->phone_code . $user->phone, $template],
                    $value
                );
            });

            $request = Http::withHeaders($headerData)
                ->timeout(15)
                ->retry(3, 100);

            $response = match (true) {
                $actionMethod === 'GET' => $request->get($actionUrl, $formData),
                $contentType === 'application/json' => $request->post($actionUrl, $formData),
                default => $request->asForm()->post($actionUrl, $formData),
            };

            if (!$response->successful()) {
                Log::warning('Notify::sendManualSms HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'user'   => $user->id ?? null,
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Notify::sendManualSms exception', [
                'error' => $e->getMessage(),
                'user'  => $user->id ?? null,
            ]);
            return false;
        }
    }

    private function withSuccess($message)
    {
        return ['success' => true, 'message' => $message];
    }

    private function withError($message)
    {
        return ['success' => false, 'message' => $message];
    }
}