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
<div class="row mb-30-none">
    <div class="col-lg-12 mb-30">
        <div class="transaction-area">
            <h4 class="title mb-0"><i class="fas fa-university text--base me-2"></i>{{ __($page_title) }}</h4>
            <div class="content pt-0">
                <div class="list-wrapper">
                    <ul class="list">
                        <li>{{ __("Company Name") }}<span>{{ $company->company_name ?? '' }}</span></li>
                        <li>{{ __("Email") }}<span>{{ $company->email ?? '' }}</span></li>
                        <li>{{ __("Username") }}<span class="text-lowercase">{{ $company->username ?? '' }}</span></li> 
                        <li>{{ __("Available Balance") }}<span>{{ get_amount($company->wallet->balance,get_default_currency_code()) }}</span></li> 
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-12 mb-30">
        <div class="transaction-area">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="title"><i class="fas fa-user text--base me-2"></i>{{ __("All Employees") }}</h4>
                <div class="d-flex">
                    <div class="button-link me-2">
                        <a href="{{ setRoute('admin.salary.disbursement.employee.list',$company->username) }}" class="btn btn--base d-flex">{{ __("Salary Disbursement") }}</a>
                    </div>
                    <div class="button-link">
                        @include('admin.components.link.add-default',[
                            'text'          => __("Add Employee"),
                            'href'          => "#employee-add",
                            'class'         => "modal-btn",
                            'permission'    => "admin.salary.disbursement.employee.store",
                        ])
                    </div>
                </div>
            </div>            
            <table class="custom-table transaction-search-table">
                <thead>
                    <tr>
                        <th>{{ __('Full Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Salary Amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($company_users ?? [] as $item)
                        <tr data-item="{{ $item }}">
                            <td><span>{{ $item->user_name ?? '' }}</span></td>
                            <td>{{ $item->user_email ?? '' }}</td>
                            <td>{{ $item->user_username ?? '' }}</td>
                            <td>{{ get_amount($item->amount,get_default_currency_code()) }}</td>
                            <td>
                                @include('admin.components.link.edit-default',[
                                    'class'         => "edit-modal-button",
                                    'permission'    => "",
                                ])
                                <button class="btn btn--base btn--danger delete-modal-button" ><i class="las la-trash-alt"></i></button>
                            </td>
                        </tr>
                    @empty
                        @include('admin.components.alerts.empty',['colspan' => 11])
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('admin.components.modals.salary-disbursement.add',compact('users'))

@include('admin.components.modals.salary-disbursement.edit')
@endsection
@push('script')
<script>
    openModalWhenError("employee-add","#employee-add")

    $(".delete-modal-button").click(function(){
        var oldData     = JSON.parse($(this).parents("tr").attr("data-item"));
        var actionRoute = "{{ setRoute('admin.salary.disbursement.employee.delete') }}";
        var target      = oldData.id;
        var message     = `{{ __("Are you sure to") }} <strong>{{ __("delete") }}</strong> {{ __("this employee?") }}`;

        openDeleteModal(actionRoute,target,message);
    });
</script>
@endpush