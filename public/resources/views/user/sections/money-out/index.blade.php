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
           <form class="card-form" method="POST" action="{{ setRoute('user.money-out.submit') }}">
            @csrf
               <div class="amount-form-header">
                   <h4 class="title">{{ __('Exchange Rate') }}</h4>
                   <h3 class="exchange-rate rate">--</h3>
               </div>
               <div class="row">
                   <div class="col-lg-6 form-group">
                       <label>{{ __('Enter Amount') }}<span>*</span></label>
                       <div class="input-group currency-type">
                           <input type="number" class="form--control" name="amount" placeholder="{{ __('Enter Amount') }}">
                           <div class="currency">
                               <p>{{ get_default_currency_code() }}</p>
                           </div>
                       </div>
                   </div>
                   <div class="col-lg-6 form-group">
                       <label>{{ __("Payment Gateway") }}<span>*</span></label>
                           <select class="select2-basic w-100" name="payment_gateway">
                            @php
                                $old_payment_gateway = old('payment_gateway');
                            @endphp
                            <option selected disabled>{{ __('Select Gateway') }}</option>
                                @foreach ($payment_gateways as $item)
                                    <option value="{{ $item->alias }}" data-item="{{ json_encode($item->currencies()->select(['name','rate','currency_code','percent_charge','fixed_charge','min_limit','max_limit'])->first()) }}" @if ($old_payment_gateway == $item->alias) @selected(true) @endif>{{ $item->name }}</option>
                                @endforeach
                           </select>
                   </div>
                   <div class="col-xl-12 col-lg-12 form-group">
                       <div class="note-area">
                           <code class="text--base limit-show">--</code>
                           <code class="text--base fees-show">--</code>
                       </div>
                   </div>
               </div>
               <div class="col-xl-12 col-lg-12">
                    <button type="submit" class="btn--base w-100 btn-loading">{{__('Money Out')}} <i class="las la-chevron-right"></i></button>
               </div>
           </form>
        </div>
         <div class="col-lg-6 mb-40">
             <div class="transfer-preview-area">
                <div class="preview-area-title pb-10">
                    <h3 class="title">{{ __('Money Out Preview') }}</h3>
                </div>
               <div class="preview-list-wrapper">
                   <div class="preview-list-item">
                       <div class="preview-list-left">
                           <div class="preview-list-user-wrapper">
                               <div class="preview-list-user-icon">
                                   <i class="las la-receipt"></i>
                               </div>
                               <div class="preview-list-user-content">
                                   <span>{{ __('Money Out Amount') }}</span>
                               </div>
                           </div>
                       </div>
                       <div class="preview-list-right">
                           <span class="text--success withdraw-amount">--</span>
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
                           <span class="text--warning total-charges">--</span>
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
           <h3 class="title">{{ __('Money Out Log') }}</h3>
           <a href="{{ setRoute('user.transactions.index', 'money-out') }}" class="btn--base">{{ __('View More') }} <i class="las la-chevron-right"></i></a>
        </div>
        <div class="transfer-log pt-3">
            <div class="dashboard-list-wrapper">
                @include('user.components.transaction.log', compact('transactions'))
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
    <script>

        let default_currency_code = "{{ get_default_currency_code() }}";

        $("select[name=payment_gateway]").change(function() {
            run();
        });

        $("input[name=amount]").keyup(function() {
            run();
        });

        function run() {
            let paymentGatewaySelect = $("select[name=payment_gateway]");
            let gatewaySelectedValue = paymentGatewaySelect.val();

            if(gatewaySelectedValue == null || gatewaySelectedValue == "") return false;

            let amount = $("input[name=amount]").val();

            let gatewayCurrency = JSON.parse(paymentGatewaySelect.find(":selected").attr("data-item"));

            (amount == null || amount == "" || !$.isNumeric(amount)) ? amount = 0 : amount = amount;

            amount = parseFloat(amount).toFixed(2);

            $(".withdraw-amount").text(`${amount} ${default_currency_code}`);

            let fixedCharge         = gatewayCurrency.fixed_charge ?? 0;
            let percentCharge       = gatewayCurrency.percent_charge ?? 0;
            let minLimit            = gatewayCurrency.min_limit ?? 0;
            let maxLimit            = gatewayCurrency.max_limit ?? 0;
            let rate                = gatewayCurrency.rate ?? 1;
            let gatewayCurrencyCode = gatewayCurrency.currency_code ?? "-";

            var min_limit_calc = parseFloat(minLimit/rate).toFixed(2);
            var max_limit_clac = parseFloat(maxLimit/rate).toFixed(2);

            $('.limit-show').html("Limit " + min_limit_calc + " " + default_currency_code + " - " + max_limit_clac + " " + default_currency_code);

            $(".exchange-rate").text(`1 ${default_currency_code} = ${parseFloat(rate).toFixed(2)} ${gatewayCurrencyCode}`);

            let fixedChargeCalc = (parseFloat(fixedCharge) / parseFloat(rate)); // default currency fixed charge
            let percentChargeCalc = ((((parseFloat(amount) * parseFloat(rate)) / 100) * parseFloat(percentCharge)) / parseFloat(rate));

            let totalCharge = parseFloat(fixedChargeCalc) + parseFloat(percentChargeCalc) // total charge in default currency
            $(".total-charges").text(`${parseFloat(totalCharge).toFixed(2)} ${default_currency_code}`);

            $(".fees-show").html("Charge: " + parseFloat(fixedChargeCalc).toFixed(2) + " " + default_currency_code + " + " + parseFloat(percentCharge).toFixed(2) + "%");

            let willGet = parseFloat(amount) * parseFloat(rate); // get amount with gateway currency
            willGet = willGet.toFixed(2);

            $('.will-get').text(`${willGet} ${gatewayCurrencyCode}`);

            let totalPayable = parseFloat(amount) + parseFloat(totalCharge);
            totalPayable = totalPayable.toFixed(2);
            $(".payable").text(`${totalPayable} ${default_currency_code}`);
        }
    </script>
@endpush
