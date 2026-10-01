<div class="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-inner-wrapper">
            <div class="sidebar-logo">
                <a href="{{ setRoute('frontend.index') }}" class="sidebar-main-logo">
                    <img src="{{ get_logo($basic_settings) }}" alt="logo">
                </a>
                <button class="sidebar-menu-bar">
                    <i class="fas fa-exchange-alt"></i>
                </button>
            </div>
            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-menu">
                    <li class="sidebar-menu-item {{ menuActive('user.dashboard') }}">
                        <a href="{{ route('user.dashboard') }}">
                            <i class="menu-icon las la-palette"></i>
                            <span class="menu-title">{{ __('Dashboard') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item" {{ menuActive('user.setup.pin.index') }}>
                        <a href="{{ setRoute('user.setup.pin.index') }}">
                            <i class="menu-icon las la-file-alt"></i>
                            <span class="menu-title">{{ __('Setup Pin') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item {{  menuActive('user.add.money.index') }}">
                        <a href="{{ setRoute('user.add.money.index') }}">
                            <i class="menu-icon las la-plus-square"></i>
                            <span class="menu-title">{{ __('Add Money') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item {{  menuActive('user.money-out.index') }}">
                        <a href="{{ setRoute('user.money-out.index') }}">
                            <i class="menu-icon las la-cloud-upload-alt"></i>
                            <span class="menu-title">{{ __('Money Out') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item {{ menuActive([
                        'user.fund-transfer.index',
                        'user.fund-transfer.create',
                        'user.fund-transfer.preview',
                        'user.fund-transfer.transaction.success'
                        ]) }}">
                        <a href="{{ route('user.fund-transfer.index') }}">
                            <i class="menu-icon las la-random"></i>
                            <span class="menu-title">{{ __('Fund Transfer') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item {{ menuActive([
                        'user.beneficiary.index',
                        'user.beneficiary.create',
                        'user.beneficiary.preview',
                        ]) }}">
                        <a href="{{ setRoute('user.beneficiary.index') }}">
                            <i class="menu-icon las la-user-plus"></i>
                            <span class="menu-title">{{ __('Beneficiary') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.strowallet.virtual.card.index') }}">
                            <i class="menu-icon fas fa-credit-card"></i>
                            <span class="menu-title">{{ __("Virtual Card") }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item {{ menuActive('user.transactions.index') }}">
                        <a href="{{ setRoute('user.transactions.index') }}">
                            <i class="menu-icon las la-history"></i>
                            <span class="menu-title">{{ __('Transactions') }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item" {{ menuActive('user.statements.index') }}>
                        <a href="{{ setRoute('user.statements.index') }}">
                            <i class="menu-icon las la-file-alt"></i>
                            <span class="menu-title">{{ __('Statement') }}</span>
                        </a>
                    </li>
                    
                    <li class="sidebar-menu-item sidebar-dropdown">
                        <a href="javascript:void(0)">
                            <i class="menu-icon las la-user-cog"></i>
                            <span class="menu-title">{{ __("Settings") }}</span>
                        </a>
                        <ul class="sidebar-submenu">
                            <li class="sidebar-menu-item">
                                <a href="{{ setRoute('user.security.google.2fa') }}" class="nav-link" >
                                    <i class="las la-ellipsis-h"></i>
                                    <span class="menu-title ms-1">{{ __('2FA Security') }}</span>
                                </a>
                                <a href="{{ setRoute('user.kyc.index') }}" class="nav-link">
                                    <i class="las la-ellipsis-h"></i>
                                    <span class="menu-title ms-1">{{ __('KYC Verification') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="javascript:void(0)" class="logout-btn">
                            <i class="menu-icon las la-sign-out-alt"></i>
                            <span class="menu-title">{{__('Logout')}}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar-doc-box bg-overlay bg_img" data-background="{{ asset('public/frontend/') }}/images/element/sidebar.webp">
            <div class="sidebar-doc-icon">
                <i class="las la-headphones-alt"></i>
            </div>
            <div class="sidebar-doc-content">
                <h4 class="title">{{ __('Help Center') }}</h4>
                <p>{{ __('How can we help you?') }}</p>
                <div class="sidebar-doc-btn">
                    <a href="{{ setRoute('user.support.ticket.index') }}" class="btn--base w-100">{{ __('Get Support') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        $(".logout-btn").click(function(){
            var actionRoute =  "{{ setRoute('user.logout') }}";
            var target      = 1;
            var message     = `{{ __("Are you sure to") }} <strong>{{ __("Logout") }}</strong>?`;

            openAlertModal(actionRoute,target,message,"{{ __('Logout') }}","POST");
                /**
            * Function for open delete modal with method DELETE
            * @param {string} URL
            * @param {string} target
            * @param {string} message
            * @returns
            */
            function openAlertModal(URL,target,message,actionBtnText = "{{ __('Remove') }}",method = "DELETE"){
            if(URL == "" || target == "") {
            return false;
            }

            if(message == "") {
            message = "Are you sure to delete ?";
            }
            var method = `<input type="hidden" name="_method" value="${method}">`;
            openModalByContent(
            {
                content: `<div class="card modal-alert border-0">
                            <div class="card-body">
                                <form method="POST" action="${URL}">
                                    <input type="hidden" name="_token" value="${laravelCsrf()}">
                                    ${method}
                                    <div class="head mb-3">
                                        ${message}
                                        <input type="hidden" name="target" value="${target}">
                                    </div>
                                    <div class="foot d-flex align-items-center justify-content-between">
                                        <button type="button" class="modal-close btn--base btn-for-modal">{{ __("Close") }}</button>
                                        <button type="submit" class="alert-submit-btn btn--base bg-danger btn-loading btn-for-modal">${actionBtnText}</button>
                                    </div>
                                </form>
                            </div>
                        </div>`,
            },

            );
            }
        });
    </script>
@endpush
