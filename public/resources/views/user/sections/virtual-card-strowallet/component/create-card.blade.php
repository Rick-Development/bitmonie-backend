<div class="col-xl-12">
    <div class="row mb-30-none">
        <div class="col-xl-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{ __(@$page_title) }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <form class="card-form row" action="{{ route('user.strowallet.virtual.card.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-xl-12 col-lg-12 form-group">
                                    <label>{{__("Card Holder's Name")}}<span>*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form--control" placeholder="{{ __("Enter Card Holder's Name") }}" name="name_on_card" value="{{ old('name_on_card') }}" required>
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12 form-group">
                                    <label>{{ __("Amount") }} <span class="text--danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group">
                                            <input type="text" class="form--control number-input" required placeholder="{{__('Enter Amount')}}" name="card_amount" value="{{ old("card_amount") }}">
                                            <select class="form--control nice-select currency" name="currency">
                                                <option value="{{ get_default_currency_code() }}">{{ get_default_currency_code() }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="note-area">
                                        <code class="d-block limit-show">--</code>
                                        <code class="d-block fees-show">--</code>
                                    </div>
                                    <code class="d-block mt-10 text-end text--dark fw-bold balance-show">{{ __("Available Balance") }} {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                                </div>
                                <div class="col-xl-12 col-lg-12">
                                    <button type="submit" class="btn--base w-100 btn-loading buyBtn">{{ __("Buy Card") }} <i class="las la-plus-circle ms-1"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{__("Preview")}}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-coins"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Card Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="fw-bold request-amount">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-battery-half"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Total Charge") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="fees">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{__("Total Payable")}}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="last payable-total text-warning">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
