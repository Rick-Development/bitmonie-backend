<nav class="navbar-wrapper">
    <div class="dashboard-title-part">
        <div class="left">
            <div class="icon">
                <button class="sidebar-menu-bar">
                    <i class="fas fa-exchange-alt"></i>
                </button>
            </div>
            <div class="dashboard-path">
                <span class="main-path"><a href="{{ setRoute('user.dashboard') }}">{{ __('Dashboard') }}</a></span>
                <i class="las la-angle-right"></i>
                <span class="active-path">{{ __($page_title) ?? 'Dashboard' }}</span>
            </div>
        </div>
        <div class="right">
            <div class="language-select me-2">
                @php
                    $__current_local = session("local") ?? get_default_language_code();
                @endphp
                <select class="nice-select" name="lang_switcher" id="">
                    @foreach ($__languages as $__item)
                        <option value="{{ $__item->code }}" @if ($__current_local == $__item->code)
                            @selected(true)
                        @endif>{{ $__item->name }}</option>
                    @endforeach
                </select>
            </div> 
            <div class="header-notification-wrapper">
                <button class="notification-icon">
                    <i class="las la-bell"></i>
                </button>
                <div class="notification-wrapper">
                    <div class="notification-header">
                        <h5 class="title">{{ __('Notification') }}</h5>
                    </div>
                    <ul class="notification-list">
                        @forelse (get_user_notifications() as $item)
                            <li>
                                <div class="thumb">
                                    <img src="{{ auth()->user()->userImage }}" alt="user">
                                </div>
                                <div class="content">
                                    <div class="title-area">
                                        <h5 class="title">{{ __($item->message->title) }}</h5>
                                        <span class="time">{{ $item->created_at->diffForHumans() }}</span>
                                    </div>
                                    <span class="sub-title">{{ __("Amount") }} : {{ get_amount($item->message->amount,$item->message->currency) }} , @if (isset($item->message->gateway))
                                        {{ __("Gateway") }} : {{ $item->message->gateway ?? '' }}
                                    @endif {{ __($item->message->message) }}</span>
                                </div>
                            </li>
                        @empty
                            <li>
                                <h5 class="text-danger">{{ __('Notification Not Found!') }}</h5>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <div class="header-user-wrapper">
                <div class="header-user-thumb">
                    <a href="{{ setRoute('user.profile.index') }}"><img src="{{ auth()->user()->userImage }}" alt="client"></a>
                </div>
            </div>
        </div>
    </div>
</nav>
@push('script')
<script>
    $("select[name=lang_switcher]").change(function(){
        var selected_value = $(this).val();
        var submitForm = `<form action="{{ setRoute('frontend.languages.switch') }}" id="local_submit" method="POST"> @csrf <input type="hidden" name="target" value="${$(this).val()}" ></form>`;
        $("body").append(submitForm);
        $("#local_submit").submit();
    });
</script>
@endpush