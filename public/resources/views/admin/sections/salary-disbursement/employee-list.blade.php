@extends('admin.layouts.master')

@push('css')

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
<div class="custom-card mb-2">
    <div class="card-header">
        <h6 class="title">{{ __($page_title) }}</h6>
    </div>
    <div class="card-body">
        <form class="card-form" action="{{ setRoute('admin.salary.disbursement.send',$username) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row mb-10-none">
                @foreach ($employee_lists ?? [] as $item)
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("Name") }}*</label>
                        <input type="text" class="form--control" value="{{ $item->user_name }}" readonly>
                        <input type="hidden" class="form--control" name="username[]" value="{{ $item->user_username }}">
                    </div>
                    <div class="col-xl-6 col-lg-6 form-group">
                        <label>{{ __("Amount") }}*</label>
                        <div class="input-group">
                            <input type="text" class="form--control number-input" name="amount[]" value="{{ get_amount($item->amount) }}" placeholder="{{ __("Enter Amount") }}">
                            <span class="input-group-text">{{ get_default_currency_code($default_currency) }}</span>
                        </div>
                    </div>
                @endforeach
                <div class="col-xl-12 col-lg-12 form-group">
                    @include('admin.components.button.form-btn',[
                        'class'         => "w-100 btn-loading",
                        'text'          => __("Update"),
                        'permission'    => "admin.salary.disbursment.send"
                    ])
                </div>
            </div>
        </form>
    </div>
</div>
@endsection