<!DOCTYPE html>
<html lang="{{ get_default_language_code() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&family=Source+Serif+4:opsz,wght@8..60,300;8..60,400;8..60,500;8..60,600;8..60,700;8..60,900&display=swap" rel="stylesheet">
    <title>{{ (isset($page_title) ? __($page_title) : __("Dashboard")) }}</title>

    @include('partials.header-asset')
    @stack("css")
</head>
<body>

    @include('frontend.partials.body-overlay')

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Dashboard
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<div class="page-wrapper" >
    @include('user.partials.side-nav')
    <div class="main-wrapper">
        <div class="main-body-wrapper">
            @include('user.partials.top-nav')
            <div class="body-wrapper">
                @yield('content')
            </div>
        </div>
    </div>
</div>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Dashboard
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->


@include('partials.footer-asset')
@include('user.partials.push-notification')

@stack("script")

<script>
    $(document).on('click', '.verify-otp-btn', function(e){
        e.preventDefault();
        sendVerificationCode("{{ setRoute('user.verification-code.send') }}",'GET', "{{ setRoute('user.verification-code.resend') }}");
    });
</script>

</body>
</html>
