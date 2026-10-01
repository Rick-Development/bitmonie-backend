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

        $(".next-btn").click(function(){
            var selectedItem = $(".dashboard-list-item-wrapper.selected");
            if(selectedItem.length == 0) {
                throwMessage("warning",['Pelase select any beneficiary']);
                return false;
            }
            var target = selectedItem.attr("data-target");
            console.log(target);
            var actionURL = "{{ setRoute('user.fund-transfer.beneficiary.select') }}";
            postFormAndSubmit(actionURL,target);
        });

        $(".dashboard-list-item-wrapper .select-btn").click(function () {
           
            $(".dashboard-list-item-wrapper").removeClass("selected");
            $(".select-btn").text("Select");

            $(this).parents(".dashboard-list-item-wrapper").toggleClass("selected");

            if ($(this).parents(".dashboard-list-item-wrapper").hasClass("selected")) {
                $(this).text("Selected");
            } else {
                $(this).text("Select");
            }
        });

        itemSearch($(".search"),$(".beneficiary-search"),"{{ setRoute('user.beneficiary.search', $type) }}",2);
    </script>
@endpush
