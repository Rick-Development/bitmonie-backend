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
    ], 'active' => __("SafeLock Plans")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.link.add-default',[
                        'text'          => "Add New",
                        'href'          => "#add-plan",
                        'class'         => "modal-btn",
                        'permission'    => "admin.savings.plans.store",
                    ])
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{__('Name')}}</th>
                            <th>{{__('Duration (Days)')}}</th>
                            <th>{{__('Interest Rate')}} (%)</th>
                            <th>{{__('Min Amount')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $item)
                            <tr data-item="{{ json_encode($item) }}">
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->duration_days }}</td>
                                <td>{{ $item->interest_rate }}%</td>
                                <td>{{ get_amount($item->min_amount, get_default_currency_code()) }}</td>
                                <td>
                                    @include('admin.components.form.switcher',[
                                        'name'          => 'status',
                                        'value'         => $item->status,
                                        'options'       => ['Active' => 1, 'Inactive' => 0],
                                        'onload'        => true,
                                        'data_target'   => $item->id,
                                        'permission'    => "admin.savings.plans.status.update",
                                    ])
                                </td>
                                <td>
                                    @include('admin.components.link.edit-default',[
                                        'href'          => "javascript:void(0)",
                                        'class'         => "edit-modal-button",
                                        'permission'    => "admin.savings.plans.update",
                                    ])
                                    @include('admin.components.link.delete-default',[
                                        'href'          => "javascript:void(0)",
                                        'class'         => "delete-modal-button",
                                        'permission'    => "admin.savings.plans.delete",
                                    ])
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add Modal --}}
    @include('admin.components.modals.add-savings-plan')

    {{-- Edit Modal --}}
    @include('admin.components.modals.edit-savings-plan')
@endsection

@push('script')
    <script>
        openModalWhenError("add-plan","#add-plan");
        openModalWhenError("edit-plan","#edit-plan");

        var default_currency = "{{ get_default_currency_code() }}";

        $(".edit-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var editModal = $("#edit-plan");

            editModal.find("form").first().attr("action",oldData.update_url);
            editModal.find("input[name=name]").val(oldData.name);
            editModal.find("input[name=duration_days]").val(oldData.duration_days);
            editModal.find("input[name=interest_rate]").val(oldData.interest_rate);
            editModal.find("input[name=min_amount]").val(oldData.min_amount);
            editModal.find("input[name=max_amount]").val(oldData.max_amount);
            
            // Set dynamic update route
            let updateUrl = `{{ setRoute('admin.savings.plans.update', ':id') }}`.replace(':id', oldData.id);
            editModal.find('form').attr('action', updateUrl);

            openModalBySelector("#edit-plan");
        });

        $('.delete-modal-button').click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var actionRoute = `{{ setRoute('admin.savings.plans.delete', ':id') }}`.replace(':id', oldData.id);
            openDeleteModal(actionRoute);
        });

        $(document).ready(function(){
            switcherAjax("{{ setRoute('admin.savings.plans.status.update') }}");
        })
    </script>
@endpush
