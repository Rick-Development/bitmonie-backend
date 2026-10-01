<div class="trx-inputs my-bank-view transaction-sub-type" style="display: block;">
    <div class="my-bank-beneficiary">
       <div class="row beneficiary-account-type-wrapper">
           <div class="col-lg-12 pb-20">
               <div class="beneficiary-account-type">
                   <label>{{__('Select Type')}} <span>*</span></label>
                   <select class="nice-select beneficiary-type-select select-scrollbar w-100" name="beneficiary_subtype">
                        <option disabled selected value="">{{ __('Select Type') }}</option>
                        @foreach (global_const()::TRX_SUB_TYPE as $key => $item)
                            <option value="{{ $key }}">{{ __($item) }}</option>
                        @endforeach
                   </select>
               </div>
           </div>
       </div>
    </div>
</div>
