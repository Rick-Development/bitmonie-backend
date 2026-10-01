@extends('user.layouts.master')
@section('content')
<div class="beneficiary-area">
    <div class="add-beneficiay-area d-flex align-items-center justify-content-end">
        <div class="header-search-wrapper">
            <div class="position-relative">
                <input class="form-control search" type="text" placeholder="{{ __("Ex: Beneficiary Full Name") }}" aria-label="Search">
                <span class="las la-search"></span>
            </div>
        </div>
        <a href="{{ setRoute('user.beneficiary.create') }}" class="btn--base"><i class="las la-plus"></i> {{ __('Add Beneficiary') }}</a>
    </div>
    <div class="beneficiary-list">
        <div class="dashboard-list-wrapper">
            <div class="items beneficiary-search">
                @include('user.components.beneficiary.items',compact("beneficiaries"))
            </div>
            {{ $beneficiaries->links() }}
        </div>
    </div>
</div>
@endsection

@push('script')
    <script>
        $(".delete-btn-rec").click(function(){
            var actionRoute =  "{{ setRoute('user.beneficiary.delete') }}";
            var message     = `{{ __('Are You Sure to Delete Beneficiary?') }}`;
            var target = $(this).data('target');
            openDeleteModal(actionRoute,target,message);
        });

        itemSearch($(".search"),$(".beneficiary-search"),"{{ setRoute('user.beneficiary.search', $type) }}",2);
    </script>
@endpush
