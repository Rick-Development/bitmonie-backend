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
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("All Companies") }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.search-input',[
                        'name'  => 'company_search',
                        'placeholder' => __("Email/Username")
                    ])
                </div>
                
            </div>
            <div class="table-responsive">
                @include('admin.components.data-table.salary-disbursement',compact('companies'))
            </div>
            {{ get_paginate($companies) }}
        </div>
    </div>
    

@endsection

@push('script')
    <script>
        itemSearch($("input[name=company_search]"),$(".company-search-table"),"{{ setRoute('admin.salary.disbursement.search') }}");
    </script>
@endpush