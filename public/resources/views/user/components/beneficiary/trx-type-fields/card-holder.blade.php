<div class="trx-inputs account-view" style="display: block;">
    <div class="row mb-10-none">
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Account Number') }} <span>*</span></label>
            <input type="number" class="form--control" name="card_number" placeholder="{{ __('Enter Card Number') }}" required>
        </div>
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Card Holder Name') }} <span>*</span></label>
            <input type="text" class="form--control" placeholder="{{ __('Enter Name') }}" name="card_holder_name" required readonly>
        </div>
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Email') }}</label>
            <input type="text" class="form--control" placeholder="{{ __('Enter Email') }}" name="email" readonly>
        </div>
    </div>
</div>
