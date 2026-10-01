@extends('user.layouts.master')

@push('css')
    <link rel="stylesheet" href="{{ asset('public/frontend/css/daterangepicker.min.css') }}">
@endpush

@section('content')
<div class="banking-statement">
    <form action="{{ setRoute('user.statements.filter') }}" method="GET">
        
    <div class="row mb-10-none">
            <div class="col-lg-3 col-md-6 mb-10">
                <label>{{ __('Filter By Date') }}</label>
                <input type="text" class="form--control daterangepicker-filed" 
                    placeholder="Select Date" 
                    readonly 
                    value="{{ (isset($_GET['from_date']) && !empty($_GET['from_date'])) || (isset($_GET['to_date']) && !empty($_GET['to_date'])) ? (isset($_GET['from_date']) ? $_GET['from_date'] : '') . (isset($_GET['from_date']) && isset($_GET['to_date']) ? ' To ' : '') . (isset($_GET['to_date']) ? $_GET['to_date'] : '') : '' }}">
                <input type="hidden" name="from_date" id="from_date" value="{{ isset($_GET['from_date']) ? $_GET['from_date'] : '' }}">
                <input type="hidden" name="to_date" id="to_date" value="{{ isset($_GET['to_date']) ? $_GET['to_date'] : '' }}">
            </div>        
            <div class="col-lg-3 col-md-6 mb-10">
                <label>{{ __('Filter By Trx Id') }}</label>
                <input type="text" name="trx_id" class="form--control" placeholder="Trx Id" value="{{ isset($_GET['trx_id']) ? $_GET['trx_id'] : '' }}">
            </div>
            <div class="col-lg-3 col-md-6 mb-10">
                <label>{{ __('Filter By Type') }}</label>
                <select class="select2-auto-tokenize w-100" name="type">
                    <option {{ isset($_GET['type']) && $_GET['type'] == "*" ? 'selected' : '' }} value="*">{{ __('All') }}</option>
                    <option {{ isset($_GET['type']) && $_GET['type'] == payment_gateway_const()::TYPEADDMONEY ? 'selected' : '' }} value="{{ payment_gateway_const()::TYPEADDMONEY }}">{{ payment_gateway_const()::TYPEADDMONEY }}</option>
                    <option {{ isset($_GET['type']) && $_GET['type'] == payment_gateway_const()::TYPEMONEYOUT ? 'selected' : ''  }} value="{{ payment_gateway_const()::TYPEMONEYOUT }}">{{ payment_gateway_const()::TYPEMONEYOUT }}</option>
                    <option {{ isset($_GET['type']) && $_GET['type'] == payment_gateway_const()::TYPEADDSUBTRACTBALANCE ? 'selected' : ''  }} value="{{ payment_gateway_const()::TYPEADDSUBTRACTBALANCE }}">{{ payment_gateway_const()::TYPEADDSUBTRACTBALANCE }}</option>
                    <option {{ isset($_GET['type']) && $_GET['type'] == 'FUND-TRANSFER' ? 'selected' : ''  }} value="FUND-TRANSFER">FUND-TRANSFER</option>
                    <option {{ isset($_GET['type']) && $_GET['type'] == payment_gateway_const()::TYPE_OWN_BANK_TRANSFER ? 'selected' : ''  }} value="{{ payment_gateway_const()::TYPE_OWN_BANK_TRANSFER }}">{{ payment_gateway_const()::TYPE_OWN_BANK_TRANSFER }}</option>
                    <option {{ isset($_GET['type']) && $_GET['type'] == payment_gateway_const()::TYPE_OTHER_BANK_TRANSFER ? 'selected' : ''  }} value="{{ payment_gateway_const()::TYPE_OTHER_BANK_TRANSFER }}">{{ payment_gateway_const()::TYPE_OTHER_BANK_TRANSFER }}</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 mb-10">
                <label>{{ __('Filter By Status') }}</label>
                <select class="select2-auto-tokenize w-100" name="status">
                    <option {{ isset($_GET['status']) && $_GET['status'] == "*" ? 'selected' : '' }} value="*">{{ __('All') }}</option>
                    <option {{ isset($_GET['status']) && $_GET['status'] == "1" ? 'selected' : '' }} value="1">{{ __('Success') }}</option>
                    <option {{ isset($_GET['status']) && $_GET['status'] == "2" ? 'selected' : '' }} value="2">{{ __('Pending') }}</option>
                    <option {{ isset($_GET['status']) && $_GET['status'] == "3" ? 'selected' : '' }} value="3">{{ __('Hold') }}</option>
                    <option {{ isset($_GET['status']) && $_GET['status'] == "4" ? 'selected' : '' }} value="4">{{ __('Rejected') }}</option>
                </select>
            </div>
            <div class="filtaring-area">
                <div class="filter-btn">
                    <button type="submit" class="btn--base"><i class="las la-filter"></i> {{ __('Filter Data') }}</button>
                </div>
                <div class="dawonload-statement">
                    <a href="{{ setRoute('user.statements.index') }}" class="btn--danger dawonload-btn"><i class="las la-download"></i>{{ __('Reset') }}</a>
                    @if (isset($transactions) && count($transactions) > 0)
                        <input type="hidden" class="submit_type">
                        <a href="#0" class="btn--base dawonload-btn pdf-button"><i class="las la-download"></i>{{ __('Download PDF') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    @isset($transactions)
    <div class="dashboard-trx-table mt-40">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>{{__('Transaction ID')}}</th>
                        <th>{{__('Transaction Type')}}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Charge') }}</th>
                        <th>{{ __('Payable Amount') }}</th>
                        <th>{{ __('Balance') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions  as $key => $item)
                    <tr>
                        <td>{{ $item->trx_id }}</td>
                        <td>{{ $item->type }}</td>
                        <td>{{ get_amount($item->request_amount, $item->request_currency) }}</td>
                        <td>
                            @if ($item->userTrxType == payment_gateway_const()::SEND)
                                {{ get_amount($item->total_charge, $item->request_currency) }}
                            @else
                            N/A
                            @endif
                        </td>
                        <td>
                            @if ($item->userTrxType == payment_gateway_const()::SEND)
                                {{ get_amount($item->total_payable, $item->request_currency) }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>{{ get_amount($item->available_balance, $item->request_currency) }}</td>
                        <td>{{ $item->userTrxType }}</td>
                        <td>
                            <span>{{ __($item->string_status->value) }}</span>
                        </td>
                        <td>{{ $item->created_at->format('d-m-y h:i:s A') }}</td>
                    </tr>
                    @empty
                        @include('admin.components.alerts.empty',['colspan' => 11])
                    @endforelse
                </tbody>
            </table>
        </div>
        <h5 class="text-end pt-2">{{ __("Total Transactions") }} : {{ @$transactions->count() }}</h5>
    </div>
    @endisset
</div>
@endsection

@push('script')
    <script src="{{ asset('public/frontend/js/moment.min.js') }}"></script>
    <script src="{{ asset('public/frontend/js/knockout-3.4.2.js') }}"></script>
    <script src="{{ asset('public/frontend/js/daterangepicker.min.js') }}"></script>

    <script>
        $('.daterangepicker-filed').daterangepicker({
            callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date   = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('input[name="from_date"]').val(start_date);
                $('input[name="to_date"]').val(end_date);

                executeLogSearch();
            }
        });

        $(document).on("click",".pdf-button", function() {
            $(this).parents("form").find(".submit_type").attr("name","submit_type").val("EXPORT");
            $(this).parents("form").submit();
        });

    </script>

@endpush
