@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 194px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 150px !important;
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
    ], 'active' => __("Setup Bank List")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.search-input',[
                        'name'  => 'bank_search',
                    ])
                    @include('admin.components.link.add-default',[
                        'text'          => __("Add Bank"),
                        'href'          => "#bank-list-add",
                        'class'         => "modal-btn",
                        'permission'    => "admin.setup-sections.section.item.store",
                    ])
                </div>
            </div>
            <div class="table-responsive">
                @include('admin.components.data-table.bank-list-table',[
                    'data'  => $banks
                ])
            </div>
        </div>
        {{ get_paginate($banks) }}
    </div>

    {{-- Currency Edit Modal --}}
    @include('admin.components.modals.edit-bank-list')

    {{-- Currency Add Modal --}}
    @include('admin.components.modals.bank-list-add')

@endsection

@push('script')
    <script>
        function keyPressCurrencyView(select) {
            var selectedValue = $(select);
            selectedValue.parents("form").find("input[name=code],input[name=currency_code]").keyup(function(){
                selectedValue.parents("form").find(".selcted-currency").text($(this).val());
            });
        }

        $(".delete-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var actionRoute =  "{{ setRoute('admin.fund-transfer.bank.list.delete') }}";
            var target      = oldData.id;
            var message     = `{{ __("Are you sure to delete") }} <strong>${oldData.name}</strong>?`;
            openDeleteModal(actionRoute,target,message);
        });

        itemSearch($("input[name=bank_search]"),$(".bank-search-table"),"{{ setRoute('admin.fund-transfer.bank.list.search') }}",1);
    </script>
@endpush
