@extends('user.layouts.master')

@section('content')
<div class="transfer-money-area pt-3">
    <div class="row mb-40-none">
        <div class="col-lg-6 mb-40">
           <div class="transfer-money-title pb-10">
               <h3 class="title">{{ __($page_title) }} {{ __("To") }} {{ $temp_data->data->beneficiary->account_holder_name }}</h3>
           </div>
           <form class="card-form" action="{{ setRoute('user.fund-transfer.submit') }}" method="POST">
                @csrf
                <input type="hidden" name="temp_token" value="{{ $token }}">
                <div class="row">
                    <div class="col-xl-12 form-group">
                        <label>{{ __('Enter Amount') }}<span>*</span></label>
                        <div class="input-group currency-type">
                            <input type="number" class="form--control" name="amount" placeholder="{{ __('Enter Amount') }}">
                            <div class="currency">
                                <p>{{ get_default_currency_code() }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <label>{{__('Remarks')}} <span>({{ __('Optional') }})</span></label>
                        <textarea class="form--control" name="remarks" placeholder="{{ __('Explain Request Purposes Here') }}…"></textarea>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <div class="note-area">
                            <code class="text--base limit-show">--</code>
                            <code class="text--base charge-show">--</code>
                        </div>
                    </div>
                </div>
                <div class="col-xl-12 col-lg-12">
                    <button type="submit" class="btn--base btn-loading w-100">{{ __('Transfer Money') }} <i class="las la-chevron-right"></i></button>
                </div>
           </form>
        </div>
        <div class="col-lg-6 mb-40">
            <div class="transfer-preview-area">
                <div class="preview-area-title pb-10">
                    <h3 class="title">{{ __('Transfer Preview') }}</h3>
                </div>
                <div class="preview-list-wrapper">
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Entered Amount') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--success enter-amount">--</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-battery-half"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Total Fees & Charges') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--warning fees">--</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="lab la-get-pocket"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Receiver Will Get') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--danger will-get">--</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-money-check-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span class="last">{{ __('Total Payable Amount') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--info last payable">--</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="transfer-preview-area mt-5">
                <div class="preview-area-title pb-10">
                    <h3 class="title">{{ __('Limit Information') }}</h3>
                </div>
                <div class="preview-list-wrapper">
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Transaction Limit') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--success">{{ get_amount($fees_and_charge->min_limit, get_default_currency_code()) }} - {{ get_amount($fees_and_charge->max_limit, get_default_currency_code()) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-battery-half"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Daily Limit') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--warning">{{ get_amount($fees_and_charge->daily_limit, get_default_currency_code()) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="lab la-get-pocket"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Remaining Daily Limit') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--danger">{{ get_amount($remaining_daily_amount,get_default_currency_code()) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-money-check-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Monthly Limit') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--info">{{ get_amount($fees_and_charge->monthly_limit,get_default_currency_code()) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-money-check-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Remaining Monthly Limit') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--info">{{ get_amount($remaining_monthly_amount,get_default_currency_code()) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
@endsection

@push('script')
    <script>
    let defaultCurrency = "{{ get_default_currency_code() }}";
    let precision = 2;
    var limitText = "{{ __('Limit') }}";
    var chargeText = "{{ __('Charge') }}";

    function run() {

        let enterAmount = $("input[name=amount]").val();
        (enterAmount == null || enterAmount == "") ? enterAmount = 0 : enterAmount = parseFloat(enterAmount);

        // get limit
        let minLimit = '{{ $fees_and_charge->min_limit }}';
        let maxLimit = '{{ $fees_and_charge->max_limit }}';
        var fixedCharge = "{{ $fees_and_charge->fixed_charge }}";
        var percentCharge = "{{ $fees_and_charge->percent_charge }}";

        // console.log(minLimit,maxLimit);
        $(".limit-show").text(`• ${limitText}  ${parseFloat(minLimit).toFixed(precision)} ${defaultCurrency} - ${parseFloat(maxLimit).toFixed(precision)} ${defaultCurrency}`);

        // get charges
        let percentChargeCalc = ((parseFloat(enterAmount) / 100) * parseFloat(percentCharge));

        $(".charge-show").text(`• ${chargeText} ${parseFloat(fixedCharge).toFixed(precision)} ${defaultCurrency} + ${parseFloat(percentCharge).toFixed(precision)}% `);

        let totalCharges = parseFloat(fixedCharge) + parseFloat(percentChargeCalc);

        // Preview Section
        $(".enter-amount").text(`${parseFloat(enterAmount).toFixed(precision)} ${defaultCurrency}`);
        $(".fees").text(`${parseFloat(totalCharges).toFixed(precision)} ${defaultCurrency}`);

        let payable = (parseFloat(enterAmount)) + (parseFloat(totalCharges));

        $(".payable").text(`${removeTrailingZeros(parseFloat(payable).toFixed(precision))} ${defaultCurrency}`);

        $(".will-get").text(`${parseFloat(enterAmount).toFixed(precision)} ${defaultCurrency}`);

        return true;
    }

    $("input[name=amount]").keyup(function() {
        run();
    });
    </script>
@endpush
