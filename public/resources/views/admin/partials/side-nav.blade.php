<div class="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-logo">
            <a href="{{ setRoute('admin.dashboard') }}" class="sidebar-main-logo">
                <img src="{{ get_logo($basic_settings) }}" data-white_img="{{ get_logo($basic_settings,'white') }}"
                data-dark_img="{{ get_logo($basic_settings,'dark') }}" alt="logo">
            </a>
            <button class="sidebar-menu-bar">
                <i class="fas fa-exchange-alt"></i>
            </button>
        </div>
        <div class="sidebar-user-area">
            <div class="sidebar-user-thumb">
                <a href="{{ setRoute('admin.profile.index') }}"><img src="{{ get_image(Auth::user()->image,'admin-profile','profile') }}" alt="user"></a>
            </div>
            <div class="sidebar-user-content">
                <h6 class="title">{{ Auth::user()->fullname }}</h6>
                <span class="sub-title">{{ Auth::user()->getRolesString() }}</span>
            </div>
        </div>
        @php
            $current_route = Route::currentRouteName();
        @endphp
        <div class="sidebar-menu-wrapper">
            <ul class="sidebar-menu">
                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.dashboard',
                    'title'     => "Dashboard",
                    'icon'      => "menu-icon las la-rocket",
                ])
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => "Default",
                    'group_links'       => [
                        [
                            'title'     => "Setup Currency",
                            'route'     => "admin.currency.index",
                            'icon'      => "menu-icon las la-coins",
                        ],
                        [
                            'title'     => "Transaction Methods",
                            'route'     => "admin.trx.settings.methods",
                            'icon'      => "menu-icon las la-transgender-alt",
                        ],
                        [
                            'title'     => "Fees & Charges",
                            'route'     => "admin.trx.settings.index",
                            'icon'      => "menu-icon las la-wallet",
                        ],
                        [
                            'title'     => "Virtual Card Api",
                            'route'     => "admin.virtual.card.api.index",
                            'icon'      => "menu-icon las la-credit-card",
                        ],
                        'dropdown'      => [
                            [
                                'title'     => "Fund Transfer",
                                'icon'      => "menu-icon las la-calculator",
                                'links'     => [
                                    [
                                        'title'     => "Bank List",
                                        'route'     => "admin.fund-transfer.bank.list.index",
                                    ],
                                    [
                                        'title'     => "Bank Branch",
                                        'route'     => "admin.fund-transfer.bank.branch.index",
                                    ],
                                ],
                            ],
                        ],
                        [
                            'title'     => "Salary Disbursement",
                            'route'     => "admin.salary.disbursement.index",
                            'icon'      => "menu-icon las la-dollar-sign",
                        ],
                    ]
                ])
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => "Transactions & Logs",
                    'group_links'       => [
                        'dropdown'      => [
                            [
                                'title'     => "Add Money Logs",
                                'icon'      => "menu-icon las la-calculator",
                                'links'     => [
                                    [
                                        'title'     => "Pending Logs",
                                        'route'     => "admin.add.money.pending",
                                    ],
                                    [
                                        'title'     => "Completed Logs",
                                        'route'     => "admin.add.money.complete",
                                    ],
                                    [
                                        'title'     => "Canceled Logs",
                                        'route'     => "admin.add.money.canceled",
                                    ],
                                    [
                                        'title'     => "All Logs",
                                        'route'     => "admin.add.money.index",
                                    ]
                                ],
                            ],
                            [
                                'title'             => "Money Out Logs",
                                'icon'              => "menu-icon las la-sign-out-alt",
                                'links'     => [
                                    [
                                        'title'     => "Pending Logs",
                                        'route'     => "admin.money.out.pending",
                                    ],
                                    [
                                        'title'     => "Completed Logs",
                                        'route'     => "admin.money.out.complete",
                                    ],
                                    [
                                        'title'     => "Canceled Logs",
                                        'route'     => "admin.money.out.canceled",
                                    ],
                                    [
                                        'title'     => "All Logs",
                                        'route'     => "admin.money.out.index",
                                    ]
                                ],
                            ],
                            [
                                'title'     => "Fund Transfer Logs",
                                'icon'      => "menu-icon las la-calculator",
                                'links'     => [
                                    [
                                        'title'     => "Pending Logs",
                                        'route'     => "admin.fund.transfer.log.pending",
                                    ],
                                    [
                                        'title'     => "Completed Logs",
                                        'route'     => "admin.fund.transfer.log.complete",
                                    ],
                                    [
                                        'title'     => "Canceled Logs",
                                        'route'     => "admin.fund.transfer.log.canceled",
                                    ],
                                    [
                                        'title'     => "All Logs",
                                        'route'     => "admin.fund.transfer.log.index",
                                    ]
                                ],
                            ],
                            [
                                'title'             => __("Virtual Card Logs"),
                                'icon'              => "menu-icon las la-wallet",
                                'links'     => [

                                    [
                                        'title'     => __("All Logs"),
                                        'route'     => "admin.virtual.card.logs",
                                    ]
                                ],
                            ],
                            [
                                'title'             => __("Salary Disbursement Logs"),
                                'icon'              => "menu-icon las la-money-bill-wave",
                                'links'     => [

                                    [
                                        'title'     => __("All Logs"),
                                        'route'     => "admin.salary.disbursement.logs.index",
                                    ]
                                ],
                            ],
                            [
                                'title'             => __("Graph USD Banking"),
                                'icon'              => "menu-icon las la-globe-americas",
                                'links'     => [
                                    [
                                        'title'     => __("USD Wallets"),
                                        'route'     => "admin.graph.wallets",
                                    ],
                                    [
                                        'title'     => __("Transactions"),
                                        'route'     => "admin.graph.transactions",
                                    ]
                                ],
                            ],
                        ],
                    ]
                ])
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => "Features",
                    'group_links'       => [
                        [
                            'title'     => "Savings",
                            'route'     => "admin.savings.index",
                            'icon'      => "menu-icon las la-piggy-bank",
                        ],
                        [
                            'title'     => "EduSave",
                            'route'     => "admin.edusave.index",
                            'icon'      => "menu-icon las la-graduation-cap",
                        ],
                        [
                            'title'     => "USDT EasyEarn",
                            'route'     => "admin.usdt.easyearn.index",
                            'icon'      => "menu-icon las la-coins",
                        ],
                        [
                            'title'     => "Gift Cards (Reloadly)",
                            'route'     => "admin.gift.card.index",
                            'icon'      => "menu-icon las la-gift",
                        ],

                        [
                            'title'     => "Ad Banners",
                            'route'     => "admin.banner.index",
                            'icon'      => "menu-icon las la-image",
                        ],
                        [
                            'title'     => "Support Tickets",
                            'route'     => "admin.support.ticket.index",
                            'icon'      => "menu-icon las la-ticket-alt",
                        ],
                        'dropdown'      => [
                            [
                                'title'     => "Bill Payment",
                                'icon'      => "menu-icon las la-file-invoice-dollar",
                                'links'     => [
                                    [
                                        'title'     => "Categories",
                                        'route'     => "admin.bill.payment.category.index",
                                    ],
                                ],
                            ],
                            [
                                'title'     => "Gift Card Trading",
                                'icon'      => "menu-icon las la-exchange-alt",
                                'links'     => [
                                    [
                                        'title'     => "Trade Submissions",
                                        'route'     => "admin.gift.card.trade.trade.index",
                                    ],
                                    [
                                        'title'     => "Categories",
                                        'route'     => "admin.gift.card.trade.category.index",
                                    ],
                                    [
                                        'title'     => "Countries",
                                        'route'     => "admin.gift.card.trade.country.index",
                                    ],
                                    [
                                        'title'     => "Rates",
                                        'route'     => "admin.gift.card.trade.rate.index",
                                    ],
                                ],
                            ],
                            [
                                'title'     => "Referral Wallet",
                                'icon'      => "menu-icon las la-wallet",
                                'links'     => [
                                    [
                                        'title'     => "Withdrawal Requests",
                                        'route'     => "admin.referral.wallet.withdrawals.pending",
                                    ],
                                ],
                            ],
                            [
                                'title'     => "Crypto Loans",
                                'icon'      => "menu-icon las la-hand-holding-usd",
                                'links'     => [
                                    [
                                        'title'     => "All Loans",
                                        'route'     => "admin.loans.index",
                                    ],
                                    [
                                        'title'     => "Lending Offers",
                                        'route'     => "admin.loans.offers",
                                    ],
                                    [
                                        'title'     => "Borrow Requests",
                                        'route'     => "admin.loans.requests",
                                    ],
                                    [
                                        'title'     => "Statistics",
                                        'route'     => "admin.loans.statistics",
                                    ],
                                    [
                                        'title'     => "Overdue Loans",
                                        'route'     => "admin.loans.overdue",
                                    ]
                                ],
                            ],
                            [
                                'title'     => "P2P Marketplace",
                                'icon'      => "menu-icon las la-exchange-alt",
                                'links'     => [
                                    [
                                        'title'     => "Ads",
                                        'route'     => "admin.p2p.ads.index",
                                    ],
                                    [
                                        'title'     => "Merchant Applications",
                                        'route'     => "admin.p2p.merchant.applications.index",
                                    ],
                                    [
                                        'title'     => "Merchant Settings",
                                        'route'     => "admin.p2p.merchant.applications.settings",
                                    ],
                                    [
                                        'title'     => "Disputes",
                                        'route'     => "admin.p2p.disputes.index",
                                    ],
                                    [
                                        'title'     => "Disclaimers",
                                        'route'     => "admin.p2p.disclaimers.index",
                                    ],
                                    [
                                        'title'     => "Risk Management",
                                        'route'     => "admin.p2p.risk.users",
                                    ]
                                ],
                            ],
                        ],
                    ]
                ])
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => "Interface Panel",
                    'group_links'       => [
                        'dropdown'      => [
                            [
                                'title'     => "User Care",
                                'icon'      => "menu-icon las la-user-edit",
                                'links'     => [
                                    [
                                        'title'     => "Active Users",
                                        'route'     => "admin.users.active",
                                    ],
                                    [
                                        'title'     => "Email Unverified",
                                        'route'     => "admin.users.email.unverified",
                                    ],
                                    [
                                        'title'     => "KYC Unverified",
                                        'route'     => "admin.users.kyc.unverified",
                                    ],
                                    [
                                        'title'     => "All Users",
                                        'route'     => "admin.users.index",
                                    ],
                                    [
                                        'title'     => "Email To Users",
                                        'route'     => "admin.users.email.users",
                                    ],
                                    [
                                        'title'     => "Banned Users",
                                        'route'     => "admin.users.banned",
                                    ]
                                ],
                            ],
                            [
                                'title'             => "Admin Care",
                                'icon'              => "menu-icon las la-user-shield",
                                'links'     => [
                                    [
                                        'title'     => "All Admin",
                                        'route'     => "admin.admins.index",
                                    ],
                                    [
                                        'title'     => "Admin Role",
                                        'route'     => "admin.admins.role.index",
                                    ],
                                    [
                                        'title'     => "Role Permission",
                                        'route'     => "admin.admins.role.permission.index",
                                    ],
                                    [
                                        'title'     => "Email To Admin",
                                        'route'     => "admin.admins.email.admins",
                                    ]
                                ],
                            ],
                        ],
                    ]
                ])
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => "Settings",
                    'group_links'       => [
                        'dropdown'      => [
                            [
                                'title'     => "Web Settings",
                                'icon'      => "menu-icon lab la-safari",
                                'links'     => [
                                    [
                                        'title'     => "Basic Settings",
                                        'route'     => "admin.web.settings.basic.settings",
                                    ],
                                    [
                                        'title'     => "Image Assets",
                                        'route'     => "admin.web.settings.image.assets",
                                    ],
                                    [
                                        'title'     => "Setup SEO",
                                        'route'     => "admin.web.settings.setup.seo",
                                    ]
                                ],
                            ],
                            [
                                'title'             => "App Settings",
                                'icon'              => "menu-icon las la-mobile",
                                'links'     => [
                                    [
                                        'title'     => "Splash Screen",
                                        'route'     => "admin.app.settings.splash.screen",
                                    ],
                                    [
                                        'title'     => "Onboard Screen",
                                        'route'     => "admin.app.settings.onboard.screens",
                                    ],
                                ],
                            ],
                        ],
                    ]
                ])
                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.languages.index',
                    'title'     => "Languages",
                    'icon'      => "menu-icon las la-language",
                ])
                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.system.maintenance.index',
                    'title'     => __("System Maintenance"),
                    'icon'      => "menu-icon las la-tools",
                ])
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => "Verification Center",
                    'group_links'       => [
                        'dropdown'      => [
                            [
                                'title'     => "Setup Email",
                                'icon'      => "menu-icon las la-envelope-open-text",
                                'links'     => [
                                    [
                                        'title'     => "Email Method",
                                        'route'     => "admin.setup.email.config",
                                    ]
                                ],
                            ],
                            [
                                'title'     => "YouVerify Config",
                                'icon'      => "menu-icon las la-id-card",
                                'links'     => [
                                    [
                                        'title'     => "Setup Keys",
                                        'route'     => "admin.web.settings.basic.settings",
                                    ],
                                    [
                                        'title'     => "KYC Tiers",
                                        'route'     => "admin.kyc.tiers.index",
                                    ]
                                ],
                            ]
                        ],
                    ]
                ])
                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.setup.kyc.index',
                    'title'     => "Setup KYC",
                    'icon'      => "menu-icon las la-clipboard-list",
                ])
                @if (admin_permission_by_name("admin.setup.sections.section"))
                    <li class="sidebar-menu-header">{{ __("Setup Web Content") }}</li>
                    @php
                        $current_url = URL::current();
                        $setup_section_childs  = [
                            setRoute('admin.setup.sections.section','banner'),
                            setRoute('admin.setup.sections.section','feature'),
                            setRoute('admin.setup.sections.section','how-it-work'),
                            setRoute('admin.setup.sections.section','security'),
                            setRoute('admin.setup.sections.section','overview'),
                            setRoute('admin.setup.sections.section','choose-us'),
                            setRoute('admin.setup.sections.section','download-app'),
                            setRoute('admin.setup.sections.section','footer'),
                            setRoute('admin.setup.sections.section','subscribe'),
                            setRoute('admin.setup.sections.section','about-us'),
                            setRoute('admin.setup.sections.section','faq'),
                            setRoute('admin.setup.sections.section','clients-feedback'),
                            setRoute('admin.setup.sections.section','services'),
                            setRoute('admin.setup.sections.section','announcement'),
                            setRoute('admin.setup.sections.section','contact-us'),
                            setRoute('admin.setup.sections.section','login'),
                            setRoute('admin.setup.sections.section','register'), 
                        ];
                    @endphp
                    <li class="sidebar-menu-item sidebar-dropdown @if (in_array($current_url,$setup_section_childs)) active @endif">
                        <a href="javascript:void(0)">
                            <i class="menu-icon las la-terminal"></i>
                            <span class="menu-title">{{ __("Setup Section") }}</span>
                        </a>
                        <ul class="sidebar-submenu">
                            <li class="sidebar-menu-item">
                                <a href="{{ setRoute('admin.setup.sections.section','banner') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','banner')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Banner Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','feature') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','feature')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Feature Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','how-it-work') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','how-it-work')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("How It Work Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','security') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','security')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Security Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','overview') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','overview')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Overview Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','choose-us') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','choose-us')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Choose Us Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','download-app') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','download-app')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Download App Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','footer') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','footer')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Footer Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','subscribe') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','subscribe')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{__("Subscribe Section")}}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','about-us') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','about-us')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("About Us Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','faq') }}" class="nav-link @if($current_url == setRoute('admin.setup.sections.section','faq')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title"> {{ __('Faq Section') }} </span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','clients-feedback') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','clients-feedback')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Clients Feedback") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','services') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','services')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Services Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','announcement') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','announcement')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Announcements") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','contact-us') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','contact-us')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Contact US Section") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','login') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','login')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{__("Login Section")}}</span>
                                </a>
                                <a href="{{ setRoute('admin.setup.sections.section','register') }}" class="nav-link @if ($current_url == setRoute('admin.setup.sections.section','register')) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{__("Register Section")}}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.setup.pages.index',
                    'title'     => "Setup Pages",
                    'icon'      => "menu-icon las la-file-alt",
                ])

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.extensions.index',
                    'title'     => "Extensions",
                    'icon'      => "menu-icon las la-puzzle-piece",
                ])

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.useful.links.index',
                    'title'     => "Useful Links",
                    'icon'      => "menu-icon las la-link",
                ])

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.subscriber.index',
                    'title'     => "Subscribers",
                    'icon'      => "menu-icon las la-bell",
                ])
                
                

                @if (admin_permission_by_name("admin.payment.gateway.view"))
                    <li class="sidebar-menu-header">{{ __("Payment Methods") }}</li>
                    @php
                        $payment_add_money_childs  = [
                            setRoute('admin.payment.gateway.view',['add-money','automatic']),
                            setRoute('admin.payment.gateway.view',['add-money','manual']),
                        ]
                    @endphp
                    <li class="sidebar-menu-item sidebar-dropdown @if (in_array($current_url,$payment_add_money_childs)) active @endif">
                        <a href="javascript:void(0)">
                            <i class="menu-icon las la-funnel-dollar"></i>
                            <span class="menu-title">{{ __("Add Money") }}</span>
                        </a>
                        <ul class="sidebar-submenu">
                            <li class="sidebar-menu-item">
                                <a href="{{ setRoute('admin.payment.gateway.view',['add-money','automatic']) }}" class="nav-link @if ($current_url == setRoute('admin.payment.gateway.view',['add-money','automatic'])) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Automatic") }}</span>
                                </a>
                                <a href="{{ setRoute('admin.payment.gateway.view',['add-money','manual']) }}" class="nav-link @if ($current_url == setRoute('admin.payment.gateway.view',['add-money','manual'])) active @endif">
                                    <i class="menu-icon las la-ellipsis-h"></i>
                                    <span class="menu-title">{{ __("Manual") }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="sidebar-menu-item @if ($current_url == setRoute('admin.payment.gateway.view',['money-out','manual'])) active @endif">
                        <a href="{{ setRoute('admin.payment.gateway.view',['money-out','manual']) }}">
                            <i class="menu-icon las la-print"></i>
                            <span class="menu-title">{{ __("Money Out") }}</span>
                        </a>
                    </li>
                @endif

                {{-- Notifications --}}
                @include('admin.components.side-nav.link-group',[
                    'group_title'       => __("Notification"),
                    'group_links'       => [
                        'dropdown'      => [
                            [
                                'title'     => __("Push Notification"),
                                'icon'      => "menu-icon las la-bell",
                                'links'     => [
                                    [
                                        'title'     => __("Setup Notification"),
                                        'route'     => "admin.push.notification.config",
                                    ],
                                    [
                                        'title'     => __("Send Notification"),
                                        'route'     => "admin.push.notification.index",
                                    ]
                                ],
                            ]
                        ],
                        [
                            'title'     => __("Contact Messages"),
                            'route'     => 'admin.contact.messages.index',
                            'icon'      => "menu-icon las la-sms",
                        ],
                    ]
                ])

                @php
                    $bonus_routes = [
                        'admin.cookie.index',
                        'admin.server.info.index',
                        'admin.cache.clear',
                    ];
                @endphp

                @if (admin_permission_by_name_array($bonus_routes))
                    <li class="sidebar-menu-header">{{ __("Bonus") }}</li>
                @endif

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.cookie.index',
                    'title'     => __("GDPR Cookie"),
                    'icon'      => "menu-icon las la-cookie-bite",
                ])

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.server.info.index',
                    'title'     => __("Server Info"),
                    'icon'      => "menu-icon las la-sitemap",
                ])

                @include('admin.components.side-nav.link',[
                    'route'     => 'admin.cache.clear',
                    'title'     => __("Clear Cache"),
                    'icon'      => "menu-icon las la-broom",
                ])
            </ul>
        </div>
    </div>
</div>
