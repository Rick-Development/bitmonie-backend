@extends('admin.layouts.master')

@push('css')
<style>
    .amount-info{
        text-align: right;
        margin-top: 10px;
    }
</style>
@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
<div class="row mb-30-none">
    <div class="col-lg-12 mb-30">
        <div class="transaction-area">
            <form class="card-form" action="{{ setRoute('admin.salary.disbursement.confirm',$identifier) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="content pt-0">
                    <div class="list-wrapper">
                        <table class="custom-table company-search-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Username') }}</th>
                                    <th>{{ __('Salary') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data['receiver_info'] ?? [] as $item)
                                    <tr>
                                        <td><span>{{ $item['username'] ?? '' }}</span></td>
                                        <td>{{ get_amount($item['amount'],get_default_currency_code()) }}</td>
                                    </tr>
                                @empty
                                    @include('admin.components.alerts.empty',['colspan' => 2])
                                @endforelse
                            </tbody>
                        </table>
                        <hr>
                        <div class="amount-info">
                            <p><strong>{{ __("Total Salary") }} : </strong>{{ get_amount($total_amount,get_default_currency_code()) }}</p>
                            <p><strong>{{ __("Available Balance") }} : </strong>{{ get_amount($available_balance,get_default_currency_code()) }}</p>
                        </div>
                    </div>
                </div>
                @if ($available_balance > $total_amount)
                    <div class="col-xl-12 col-lg-12 form-group mt-2">
                        @include('admin.components.button.form-btn',[
                            'class'         => "w-100 btn-loading",
                            'text'          => __("Confirm"),
                            'permission'    => "admin.salary.disbursment.confirm"
                        ])
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection