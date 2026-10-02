@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="transfer-money-area pt-3">
    <div class="row mb-40-none">
        <div class="col-lg-6 mb-40">
           <div class="transfer-money-title pb-10">
               <h3 class="title">{{ __($page_title) }}</h3>
           </div>
           <form class="card-form" method="POST" action="{{ setRoute('user.add.money.submit') }}">
                @csrf
               <div class="amount-form-header">
                   <h4 class="title">{{ __('Exchange Rate') }}</h4>
                   <h3 class="rate exchange-rate-show">--</h3>
               </div>
               <div class="row">
                   <div class="col-lg-6 form-group">
                       <label>{{ __('Enter Amount') }}<span>*</span></label>
                       <div class="input-group currency-type">
                           <input type="text" class="form-control" placeholder="{{ __('Enter Amount') }}" name="amount" maxlength="20">
                           <div class="currency">
                               <p>{{ get_default_currency_code() }}</p>
                           </div>
                       </div>
                   </div>
                   <div class="col-lg-6 form-group">
                       <label>{{ __('Payment Gateway') }}<span>*</span></label>
                           <select class="form-control select-item-2 py-0 w-100 select2-basic" name="gateway_currency">
                            <option value="" selected disabled>{{ __('Choose Gateway') }}</option>
                            @foreach ($payment_gateways ?? [] as $gateway)
                                @foreach ($gateway->currencies as $currency)
                                    <option data-item="{{ $currency->getOnly(['currency_code','rate','min_limit','max_limit','percent_charge','fixed_charge','crypto'])->makeJson() }}" value="{{ $currency->alias }}">{{ $gateway->name . " " . $currency->currency_code }} @if ($gateway->isManual()) (Manual)@endif</option>
                                @endforeach
                            @endforeach
                        </select>
                   </div>
                   <div class="col-xl-12 col-lg-12 form-group">
                       <div class="note-area">
                           <code class="text--base limit-show">--</code>
                           <code class="text--base charge-show">--</code>
                       </div>
                   </div>
               </div>
               <div class="col-xl-12 col-lg-12">
                    <button type="submit" class="btn--base w-100 btn-loading">{{ __('Add Amount') }} <i class="las la-chevron-right"></i></button>
               </div>

           </form>
        </div>
         <div class="col-lg-6 mb-40">
             <div class="transfer-preview-area">
                <div class="preview-area-title pb-10">
                    <h3 class="title">{{ __('Add Money Preview') }}</h3>
                </div>
               <div class="preview-list-wrapper">
                   <div class="preview-list-item">
                       <div class="preview-list-left">
                           <div class="preview-list-user-wrapper">
                               <div class="preview-list-user-icon">
                                   <i class="las la-receipt"></i>
                               </div>
                               <div class="preview-list-user-content">
                                   <span>{{ __('Request Amount') }}</span>
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
                                <i class="las la-sync-alt"></i>
                            </div>
                            <div class="preview-list-user-content">
                                <span>{{ __('Exchange Rate') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-list-right">
                        <span class="text--warning exchange-rate">--</span>
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
                                   <span>{{ __('Will Get') }}</span>
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
         </div>
    </div>
    <div class="transfer-money-log pt-80">
        <div class="title d-flex justify-content-between">
           <h3 class="title">{{__('Add Money Log')}}</h3>
           <a href="{{ setRoute('user.transactions.index', 'add-money') }}" class="btn--base">{{ __('View More') }} <i class="las la-chevron-right"></i></a>
        </div>
         <div class="transfer-log pt-3">
            @include('user.components.transaction.log')
         </div>
    </div>
</div>
@endsection

@push('script')
<script>

    // Verify Otp
    let defaultCurrency = "{{ get_default_currency_code() }}";
    let precision = 2;

    $("select[name=gateway_currency]").change(function() {
        var selectedItem = $(this).find(":selected");
        var currency = JSON.parse(selectedItem.attr("data-item"));

        run(currency);
    });

    $(".submit-form").submit(function(e) {
        e.preventDefault();
        let selectedCurrency = $("select[name=gateway_currency]").find(":selected");
        let result = false;
        if(selectedCurrency.length > 0) {
            result = run(selectedCurrency.attr("data-item"));
        }

        if(result == true) {
            $(this).find("button[type=submit]").click();
            $(this).unbind('submit').submit();
        }
    });

    function run(currency) {

        if(currency == "" || currency == null) {
            return false;
        }

        if(typeof currency == "string") {
            try {
                currency = JSON.parse(currency);
            } catch (error) {
                throwMessage('error',['Unaccepted Data Format!']);
                return false;
            }
        }

        if(!$.isNumeric(currency.min_limit) || !$.isNumeric(currency.max_limit) || !$.isNumeric(currency.rate) || !$.isNumeric(currency.percent_charge) || !$.isNumeric(currency.fixed_charge)) {
            throwMessage('error',['Unaccepted Data Format!']);
            return false;
        }

        let enterAmount = $("input[name=amount]").val();
        (enterAmount == null || enterAmount == "") ? enterAmount = 0 : enterAmount = parseFloat(enterAmount);

        // get limit
        let minLimit = parseFloat(currency.min_limit / currency.rate);
        let maxLimit = parseFloat(currency.max_limit / currency.rate);

        // console.log(minLimit,maxLimit);
        $(".limit-show").text(`• Limit ${parseFloat(minLimit).toFixed(precision)} ${defaultCurrency} - ${parseFloat(maxLimit).toFixed(precision)} ${defaultCurrency}`);

        // get charges
        let percentChargeCalc = (((parseFloat(enterAmount) * parseFloat(currency.rate)) / 100) * parseFloat(currency.percent_charge) / parseFloat(currency.rate));

        let fixedChargeCalc = parseFloat(currency.fixed_charge);

        $(".charge-show").text(`• Charge ${parseFloat(fixedChargeCalc).toFixed(precision)} ${defaultCurrency} + ${parseFloat(currency.percent_charge).toFixed(precision)}% `);

        let totalCharges = parseFloat(fixedChargeCalc) + parseFloat(percentChargeCalc);

        $(".exchange-rate-show").text(`• Rate 1.00 ${defaultCurrency} = ${removeTrailingZeros(parseFloat(currency.rate).toString())} ${currency.currency_code}`);

        // Preview Section
        $(".enter-amount").text(`${parseFloat(enterAmount).toFixed(precision)} ${defaultCurrency}`);
        $(".fees").text(`${parseFloat(totalCharges).toFixed(precision)} ${defaultCurrency}`);

        let payable = (parseFloat(enterAmount) * parseFloat(currency.rate)) + (parseFloat(totalCharges) * parseFloat(currency.rate));

        $(".payable").text(`${removeTrailingZeros(parseFloat(payable).toFixed(precision))} ${currency.currency_code}`);

        $(".will-get").text(`${parseFloat(enterAmount).toFixed(precision)} ${defaultCurrency}`);

        $(".exchange-rate").text(`1.00 ${defaultCurrency} = ${removeTrailingZeros(parseFloat(currency.rate).toString())} ${currency.currency_code}`);

        return true;
    }

    $("input[name=amount]").keyup(function() {
        let selectedCurrency = $("select[name=gateway_currency]").find(":selected");
        if(selectedCurrency.length > 0) {
            run(selectedCurrency.attr("data-item"));
        }
    });

</script>


@endpush
