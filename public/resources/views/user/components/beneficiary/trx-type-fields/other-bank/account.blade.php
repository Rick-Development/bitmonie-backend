<div class="trx-inputs account-view" style="display: block;">
    <div class="row mb-10-none">
        <div class="col-xl-4 col-lg-4 col-md-4 form-group">
            <label>{{ __("Select Bank") }}<span>*</span></label>
            <select class="form--control" name="bank">
                <option selected disabled>{{ __('Select Bank') }}</option>
                @foreach ($bank_list as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-4 col-lg-4 col-md-4 form-group">
            <label>{{ __("Select Branch") }}<span>*</span></label>
            <select class="form--control" name="branch">
                <option selected disabled>{{ __('Select Branch') }}</option>
            </select>
        </div>
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Account Number') }} <span>*</span></label>
            <input type="number" class="form--control" name="account_number" placeholder="{{ __('Enter Account Number') }}" required>
        </div>
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Account Holder Name') }} <span>*</span></label>
            <input type="text" class="form--control" placeholder="{{ __('Enter Name') }}" name="account_holder_name" required>
        </div>
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Email') }}</label>
            <input type="email" class="form--control" placeholder="{{ __('Enter Email') }}" name="email">
        </div>
        <div class="col-lg-4 col-md-6 mb-10">
            <label>{{ __('Phone') }}</label>
            <input type="number" class="form--control" placeholder="{{ __('Enter Phone') }}" name="phone">
        </div>
    </div>
</div>
