<?php

namespace App\Traits;

use App\Events\AdminNotification;
use App\Events\UserNotification;
use App\Mail\SendMail;
use App\Models\Admin;
use App\Models\FireBaseToken;
use App\Models\InAppNotification;
use App\Models\ManualSmsConfig;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Facades\App\Services\BasicCurl;
use Facades\App\Services\SMS\BaseSmsService;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Artisan;

trait Notify
{
    public function sendMailSms($user, $templateKey, $params = [], $subject = null, $requestMessage = null)
    {
        $this->mail($user, $templateKey, $params, $subject, $requestMessage);
        $this->sms($user, $templateKey, $params, $requestMessage = null);
    }

    public function mail($user, $templateKey = null, $params = [], $subject = null, $requestMessage = null)
    {

        try {
            $basic = basicControl();
            if (in_array($templateKey, $user->notifypermission->template_email_key) == false) {
                return false;
            }

            if ($basic->email_notification != 1) {
                return false;
            }
            $email_body = $basic->email_description;

            $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('language_id', $user->language_id)->where('notify_for', 0)->first();
            if (!$templateObj) {
                $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('notify_for', 0)->first();
            }

            $message = str_replace("[[name]]", $user->firstname, $email_body);


            if (!$templateObj && $subject == null) {
                return false;
            } else {
                if ($templateObj) {
                    $message = str_replace("[[message]]", $templateObj->email, $message);
                    if (empty($message)) {
                        $message = $email_body;
                    }
                    foreach ($params as $code => $value) {
                        $message = str_replace('[[' . $code . ']]', $value, $message);
                    }
                } else {
                    $message = str_replace("[[message]]", $requestMessage, $message);
                }
            }

            $subject = ($subject == null) ? $templateObj->subject : $subject;
            $email_from = $basic->sender_email;

            Mail::to($user)->queue(new SendMail($email_from, $subject, $message));
            Artisan::call('queue:work', ['--stop-when-empty' => true]);
        } catch (\Exception $exception) {
            return true;
        }
    }

    public function sms($user, $templateKey, $params = [], $requestMessage = null)
    {
        try {
            $basic = basicControl();
            if (in_array($templateKey, $user->notifypermission->template_sms_key) == false) {
                return false;
            }
            if ($basic->sms_notification != 1) {
                return false;
            }
            $smsControl = ManualSmsConfig::firstOrCreate();

            $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('language_id', $user->language_id)->where('notify_for', 0)->first();
            if (!$templateObj) {
                $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('notify_for', 0)->first();
            }
            if (!$templateObj) {
                return 0;
            }

            if (!$templateObj->status['sms']) {
                return false;
            }

            if (!$templateObj && $requestMessage == null) {
                return false;
            } else {
                if ($templateObj) {
                    $template = $templateObj->sms;
                    foreach ($params as $code => $value) {
                        $template = str_replace('[[' . $code . ']]', $value, $template);
                    }
                } else {
                    $template = $requestMessage;
                }
            }

            if (config('SMSConfig.default') == 'manual') {
                $headerData = is_null($smsControl->header_data) ? [] : json_decode($smsControl->header_data, true);
                $paramData = is_null($smsControl->header_data) ? [] : json_decode($smsControl->header_data, true);
                $paramData = http_build_query($paramData);
                $actionUrl = $smsControl->header_data;
                $actionMethod = $smsControl->action_method;
                $formData = is_null($smsControl->form_data) ? [] : json_decode($smsControl->form_data, true);
                $formData = isset($headerData['Content-Type']) && $headerData['Content-Type'] == "application/x-www-form-urlencoded" ? http_build_query($formData) : (isset($headerData['Content-Type']) && $headerData['Content-Type'] == "application/json" ? json_encode($formData) : $formData);

                foreach ($headerData as $key => $data) {
                    $headerData[] = "{$key}:$data";
                }

                if ($actionMethod == 'GET') {
                    $actionUrl = $actionUrl . '?' . $paramData;
                }

                $formData = recursive_array_replace("[[receiver]]", $user->phone, recursive_array_replace("[[message]]", $template, $formData));
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $actionUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => $actionMethod,
                    CURLOPT_POSTFIELDS => $formData,
                    CURLOPT_HTTPHEADER => $headerData
                ));

                $response = curl_exec($curl);
                curl_close($curl);
                return $response;
            } else {
                BaseSmsService::sendSMS($user->phone_code . $user->phone, $template);
                return true;
            }
        } catch (\Exception $exception) {
            return true;
        }
    }

    public function sendWhatsAppMessage($otp)
    {
        $message = "$otp is your verification OTP. Don't share this code";

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $whatsapp_number = config('services.twilio.whatsapp_number');

        $user = auth()->user();
        $recipientNumber = "whatsapp:".$user->phone_code.$user->phone;
        //$recipientNumber = "whatsapp:+8801871344252";

        try {
            $twilio = new Client($sid, $token);
            $twilio->messages->create(
                $recipientNumber,
                [
                    "from" => $whatsapp_number,
                    "body" => $message,
                ]
            );
            return response()->json($this->withSuccess('WhatsApp message sent successfully'));
        } catch (\Exception $e) {
            return response()->json($this->withError($e->getMessage()));
        }
    }


    public function verifyToMail($user, $templateKey = null, $params = [], $subject = null, $requestMessage = null)
    {

        $basic = basicControl();

        if ($basic->email_verification != 1) {
            return false;
        }

        $email_body = $basic->email_description;
        $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('language_id', $user->language_id)->first();
        if (!$templateObj) {
            $templateObj = NotificationTemplate::where('template_key', $templateKey)->first();
        }
        $message = str_replace("[[name]]", $user->firstname, $email_body);

        if (!$templateObj && $subject == null) {
            return false;
        } else {
            if ($templateObj) {
                $message = str_replace("[[message]]", $templateObj->email, $message);
                if (empty($message)) {
                    $message = $email_body;
                }
                foreach ($params as $code => $value) {
                    $message = str_replace('[[' . $code . ']]', $value, $message);
                }
            } else {
                $message = str_replace("[[message]]", $requestMessage, $message);
            }
        }

        $subject = ($subject == null) ? $templateObj->subject : $subject;
        $email_from = ($templateObj) ? $templateObj->email_from : $basic->sender_email;

        Mail::to($user)->queue(new SendMail($email_from, $subject, $message));
        Artisan::call('queue:work', ['--stop-when-empty' => true]);
    }

    public function verifyToSms($user, $templateKey, $params = [], $requestMessage = null)
    {
        $basic = basicControl();
        if ($basic->sms_verification != 1) {
            return false;
        }

        $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('language_id', $user->language_id)->first();
        if (!$templateObj) {
            $templateObj = NotificationTemplate::where('template_key', $templateKey)->first();
        }

        if (!$templateObj && $requestMessage == null) {
            return false;
        } else {
            if ($templateObj) {
                $template = $templateObj->sms;
                foreach ($params as $code => $value) {
                    $template = str_replace('[[' . $code . ']]', $value, $template);
                }
            } else {
                $template = $requestMessage;
            }
        }

        $smsControl = ManualSmsConfig::firstOrCreate(['id' => 1]);
        if (config('SMSConfig.default') == 'manual') {
            $headerData = is_null($smsControl->header_data) ? [] : json_decode($smsControl->header_data, true);
            $paramData = is_null($smsControl->header_data) ? [] : json_decode($smsControl->header_data, true);
            $paramData = http_build_query($paramData);
            $actionUrl = $smsControl->header_data;
            $actionMethod = $smsControl->action_method;
            $formData = is_null($smsControl->form_data) ? [] : json_decode($smsControl->form_data, true);
            $formData = isset($headerData['Content-Type']) && $headerData['Content-Type'] == "application/x-www-form-urlencoded" ? http_build_query($formData) : (isset($headerData['Content-Type']) && $headerData['Content-Type'] == "application/json" ? json_encode($formData) : $formData);

            foreach ($headerData as $key => $data) {
                $headerData[] = "{$key}:$data";
            }

            if ($actionMethod == 'GET') {
                $actionUrl = $actionUrl . '?' . $paramData;
            }

            $formData = recursive_array_replace("[[receiver]]", $user->phone, recursive_array_replace("[[message]]", $template, $formData));
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $actionUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $actionMethod,
                CURLOPT_POSTFIELDS => $formData,
                CURLOPT_HTTPHEADER => $headerData
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            return $response;
        } else {
            BaseSmsService::sendSMS($user->phone_code . $user->phone, $template);
            return true;
        }
    }

    public function userFirebasePushNotification($user, $templateKey, $params = [], $action = null)
    {
        try {
            $basic = basicControl();
            $notify = config('firebase');
            if (empty($user->notifypermission) || in_array($templateKey, $user->notifypermission->template_push_key) == false) {
                return false;
            }
            if (!$basic->push_notification) {
                return false;
            }
            if ($notify['user_foreground'] == 0 && $notify['user_background'] == 0) {
                return false;
            }
            $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('language_id', $user->language_id)->first();
            if (!$templateObj->status['push']) {
                return false;
            }
            if (!$templateObj) {
                $templateObj = NotificationTemplate::where('template_key', $templateKey)->first();
                if (!$templateObj->status['push']) {
                    return false;
                }
            }
            $template = '';
            if ($templateObj) {
                $template = $templateObj->push;
                foreach ($params as $code => $value) {
                    $template = str_replace('[[' . $code . ']]', $value, $template);
                }
            }
            $users = FireBaseToken::where('tokenable_id', $user->id)->get();
            foreach ($users as $user) {
                $data = [
                    "to" => $user->token,
                    "notification" => [
                        "title" => $templateObj->name . ' from ' . ($basic?->site_title ?: config('app.name', 'Bitmonie')),
                        "body" => $template,
                        "icon" => asset("icon" => asset(basicControl()->favicon),),, "icon" => asset(basicControl()->favicon),),
                    ],
                    "data" => [
                        "foreground" => (int)$notify['user_foreground'],
                        "background" => (int)$notify['user_background'],
                        "click_action" => $action
                    ],
                    "content_available" => true,
                    "mutable_content" => true
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $notify['serverKey']
                ])
                    ->acceptJson()
                    ->post('https://fcm.googleapis.com/fcm/send', $data);
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function userPushNotification($user, $templateKey, $params = [], $action = [])
    {
        try {
            $basic = basicControl();
            if ($basic->in_app_notification != 1) {
                return false;
            }
            if (in_array($templateKey, $user->notifypermission->template_in_app_key) == false) {
                return false;
            }

            $templateObj = NotificationTemplate::where('template_key', $templateKey)
                ->where('language_id', $user->language_id)->where('notify_for', 0)
                ->first();
            if (!$templateObj) {
                $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('notify_for', 0)->first();
                if (!$templateObj || !$templateObj->status['in_app']) {
                    return false;
                }
            }
            if ($templateObj) {
                $template = $templateObj->in_app;
                foreach ($params as $code => $value) {
                    $template = str_replace('[[' . $code . ']]', $value, $template);
                }
                $action['text'] = $template;
            }
            $inAppNotification = new InAppNotification();
            $inAppNotification->description = $action;
            $user->inAppNotification()->save($inAppNotification);
            event(new UserNotification($inAppNotification, $user->id));
        } catch (\Exception $e) {
            return 0;
        }

    }

    public function adminFirebasePushNotification($templateKey, $params = [], $action = null)
    {
        try {
            $basic = basicControl();
            $notify = config('firebase');
            if (!$notify) {
                return false;
            }
            if (!$basic->push_notification) {
                return false;
            }
            $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('notify_for', 1)->first();
            if (!$templateObj->status['push']) {
                return false;
            }
            if (!$templateObj) {
                return false;
            }
            $template = '';
            if ($templateObj) {
                $template = $templateObj->push;
                foreach ($params as $code => $value) {
                    $template = str_replace('[[' . $code . ']]', $value, $template);
                }
            }
            $admins = FireBaseToken::where('tokenable_type', Admin::class)->get();

            foreach ($admins as $admin) {
                $data = [
                    "to" => $admin->token,
                    "notification" => [
                        "title" => $templateObj->name,
                        "body" => $template,
                        "icon" => asset("icon" => asset(basicControl()->favicon),),, "icon" => asset(basicControl()->favicon),),
                        "data" => [
                            "foreground" => (int)$notify['admin_foreground'],
                            "background" => (int)$notify['admin_background'],
                            "click_action" => $action['link']
                        ],
                        "content_available" => true,
                        "mutable_content" => true
                    ]
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $notify['serverKey']
                ])
                    ->acceptJson()
                    ->post('https://fcm.googleapis.com/fcm/send', $data);
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function adminPushNotification($templateKey, $params = [], $action = [])
    {

        try {
            $basic = basicControl();
            if ($basic->in_app_notification != 1) {
                return false;
            }

            $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('notify_for', 1)->first();
            if (!$templateObj->status['in_app']) {
                return false;
            }

            if ($templateObj) {
                $template = $templateObj->in_app;
                foreach ($params as $code => $value) {
                    $template = str_replace('[[' . $code . ']]', $value, $template);
                }
                $action['text'] = $template;
            }

            $admins = Admin::all();
            foreach ($admins as $admin) {
                $inAppNotification = new InAppNotification();
                $inAppNotification->description = $action;
                $admin->inAppNotification()->save($inAppNotification);
                event(new AdminNotification($inAppNotification, $admin->id));
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function adminMail($templateKey = null, $params = [], $subject = null, $requestMessage = null)
    {
        $basic = basicControl();

        if ($basic->email_notification != 1) {
            return false;
        }

        $email_body = $basic->email_description;
        $templateObj = NotificationTemplate::where('template_key', $templateKey)->where('notify_for', 1)->first();
        if (!$templateObj->status['mail']) {
            return false;
        }

        $message = $email_body;
        if ($templateObj) {
            $message = str_replace("[[message]]", $templateObj->email, $message);

            if (empty($message)) {
                $message = $email_body;
            }
            foreach ($params as $code => $value) {
                $message = str_replace('[[' . $code . ']]', $value, $message);
            }
        } else {
            $message = str_replace("[[message]]", $requestMessage, $message);
        }

        $subject = ($subject == null) ? $templateObj->subject : $subject;
        $email_from = $basic->sender_email;
        $admins = Admin::all();
        foreach ($admins as $admin) {
            $message = str_replace("[[name]]", $admin->firstname, $message);
            Mail::to($admin)->queue(new SendMail($email_from, $subject, $message));
        }
        Artisan::call('queue:work', ['--stop-when-empty' => true]);
    }


    public function loginNotify($user): void
    {
        try {
            $params = ['user' => $user->firstname,];

            $action = [
                "link" => "#",
                "icon" => "fa fa-user text-white"
            ];
            $firebaseAction = '#';
            $this->mail($user, 'USER_LOGIN', $params);
            // $this->sendMailSms($user, 'USER_LOGIN', $params);
            // $this->userPushNotification($user, 'USER_LOGIN', $params, $action);
            // $this->userFirebasePushNotification($user, 'USER_LOGIN', $params, $firebaseAction);
        } catch (\Exception $e) {

        }
    }


    public function sendWelcomeEmail($user): void
    {
        try {
            $params = ['user' => $user->firstname,];
            $this->mail($user, 'WELCOME_NEW_USER', $params);
        } catch (\Exception $e) {

        }
    }


}