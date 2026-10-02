<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name', 'Bitmonie') }}</title>


<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 30px 15px;
        background: #f4f6f9;
        font-family: Arial, Helvetica, sans-serif;
        color: #333333;
        line-height: 1.7;
    }

    .wrapper {
        width: 100%;
    }

    .container {
        width: 100%;
        max-width: 620px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    .header {
        background: #5a5278;
        padding: 28px 30px;
        text-align: center;
    }

    .header h1 {
        margin: 0;
        color: #ffffff;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.3;
    }

    .content {
        padding: 40px 35px;
        font-size: 15px;
    }

    .greeting {
        margin-bottom: 20px;
        color: #333333;
        font-size: 18px;
        font-weight: 600;
    }

    .email-message {
        background: #f7f8fc;
        border-left: 4px solid #5a5278;
        padding: 22px;
        margin: 20px 0 25px;
        border-radius: 5px;
        color: #444444;
    }

    .email-message p {
        margin-bottom: 14px;
    }

    .email-message p:last-child {
        margin-bottom: 0;
    }

    .button-wrapper {
        margin: 25px 0;
        text-align: center;
    }

    .button {
        display: inline-block;
        padding: 12px 26px;
        background: #5a5278;
        color: #ffffff !important;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 700;
        font-size: 14px;
    }

    .security-notice {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #eeeeee;
        color: #777777;
        font-size: 13px;
    }

    .closing {
        margin-top: 25px;
    }

    .footer {
        background: #fafafa;
        border-top: 1px solid #ececec;
        padding: 25px 20px;
        text-align: center;
        font-size: 13px;
        color: #777777;
    }

    .footer a {
        color: #5a5278;
        text-decoration: none;
        font-weight: 700;
    }

    .footer .automated {
        margin-top: 8px;
        color: #999999;
        font-size: 12px;
    }

    @media (max-width: 600px) {
        body {
            padding: 15px 10px;
        }

        .container {
            border-radius: 7px;
        }

        .header {
            padding: 22px 20px;
        }

        .header h1 {
            font-size: 21px;
        }

        .content {
            padding: 28px 22px;
        }

        .greeting {
            font-size: 17px;
        }

        .email-message {
            padding: 18px;
        }

        .button {
            display: block;
            width: 100%;
            text-align: center;
        }
    }
</style>


</head>

<body>

<div class="wrapper">


<div class="container">

    <div class="header">
        <h1>
            {{ $site_name ?? config('app.name', 'Bitmonie') }}
        </h1>
    </div>

    <div class="content">

        <div class="greeting">
            Hello {{ $user->firstname ?? 'User' }},
        </div>

        <div class="email-message">
            {!! $messageBody ?? '' !!}
        </div>

        @if(!empty($actionUrl))
            <div class="button-wrapper">
                <a href="{{ $actionUrl }}" class="button">
                    {{ $actionText ?? 'Continue' }}
                </a>
            </div>
        @endif

        <div class="security-notice">
            If you did not initiate this request, you can safely ignore this email.
            If you have any concerns about your account, please contact our support team.
        </div>

        <div class="closing">
            Thank you,<br>
            <strong>{{ $site_name ?? config('app.name', 'Bitmonie') }}</strong>
        </div>

    </div>

    <div class="footer">

        &copy; {{ date('Y') }}

        <a href="{{ $site_url ?? config('app.url') }}">
            {{ $site_name ?? config('app.name', 'Bitmonie') }}
        </a>

        <div class="automated">
            This is an automated email. Please do not reply to this message.
        </div>

    </div>

</div>


</div>

</body>
</html>
