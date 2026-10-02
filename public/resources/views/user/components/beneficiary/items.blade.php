@if (isset($beneficiaries))
    @forelse ($beneficiaries ?? [] as $item)
        <div class="dashboard-list-item-wrapper" data-target="{{ @$item->id }}">
            <div class="dashboard-list-item sent">
                <div class="dashboard-list-left">
                    <div class="dashboard-list-user-wrapper">
                        <div class="dashboard-list-user-icon">
                            <i class="las la-arrow-up"></i>
                        </div>
                        <div class="dashboard-list-user-content">
                            @if (@$item->info->beneficiary_subtype == global_const()::TRX_ACCOUNT_NUMBER)
                                <h4 class="title">{{ @$item->info->account_holder_name }}</h4>
                            @else
                                <h4 class="title">{{ @$item->info->card_holder_name }}</h4>
                            @endif
                            <span class="sub-title">{{ @$item->method->readableName }}</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-list-right-btn">
                    @if (@$type == global_const()::FUND_TRANSFER)
                        <button type="button" class="btn--base select-btn">{{ __("Select") }}</button>
                    @else
                        <button type="button" class="btn btn--base delate-btn delete-btn-rec" data-target="{{ @$item->id }}"><i class="las la-trash-alt"></i></button>
                    @endif
                </div>
            </div>
            <div class="preview-list-wrapper">
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-wallet"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __("Transaction Type") }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--danger">{{ @$item->method->readableName }}</span>
                    </div>
                </div>
                <div class="preview-list-item">
                    <div class="preview-list-left">
                        <div class="preview-list-user-wrapper">
                            <div class="preview-list-user-icon">
                                <i class="las la-file-invoice"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __('Beneficiary Sub Type') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span>{{ global_const()::TRX_SUB_TYPE[@$item->info->beneficiary_subtype] }}</span>
                    </div>
                </div>
                @if (@$item->method->name == global_const()::TRX_OWN_BANK_TRANSFER)
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-exchange-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Account Holder Name") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->account_holder_name}}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-exchange-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Account Number") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->account_number}}</span>
                        </div>
                    </div> 
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-user-circle"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Account Holder Email") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->email ?? "N/A"  }}</span>
                        </div>
                    </div> 
                @elseif(@$item->method->name == global_const()::TRX_OTHER_BANK_TRANSFER)
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-university"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Bank name') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->bank->name }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-building"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Branch Name') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->branch->name }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-address-card"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Account Number') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->account_number }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-id-card-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Account Holder Name') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->account_holder_name }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-user-circle"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Account Holder Email") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->email ?? "N/A"  }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-user-circle"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __("Account Holder Phone") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span>{{ $item->info->phone ?? "N/A"  }}</span>
                        </div>
                    </div>
                @endif
                
            </div>
        </div>
        @empty
        <div class="alert alert-primary text-center">{{ __("No Record Found!") }}</div>
    @endforelse

    @if (@$type == global_const()::FUND_TRANSFER)
        <div class="beneficiary-area-btn pt-30">
            <button type="button" class="btn--base next-btn btn-loading w-100">{{ __('Next') }} <i class="las la-chevron-right"></i></button>
        </div>
    @endif
@endif
