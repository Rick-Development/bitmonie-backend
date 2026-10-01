@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 194px !important;
        }

        .fileholder-single {
            min-height: 194px !important;
        }
    </style>
@endpush

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', ['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("VAS Categories")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("VAS Service Categories") }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.search-input', [
                        'name'  => 'vas_category_search',
                    ])
                    <button class="btn btn--primary addBtn"><i class="las la-plus"></i> {{ __("Add New") }}</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("Service") }}</th>
                            <th>{{ __("Name") }}</th>
                            <th>{{ __("Identifier (SafeHaven ID)") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Action") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $item)
                            <tr>
                                <td>{{ $item->service->name }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->identifier }}</td>
                                <td>
                                    @include('admin.components.form.switcher', [
                                        'name'          => 'status',
                                        'value'         => $item->status,
                                        'options'       => [__('Enable') => 1, __('Disable') => 0],
                                        'onload'        => true,
                                        'data_target'   => $item->id,
                                        'permission'    => "admin.bill.payment.category.status",
                                    ])
                                </td>
                                <td>
                                    <button class="btn btn--base editBtn" data-item="{{ json_encode($item) }}"><i class="las la-pencil-alt"></i></button>
                                    <button class="btn btn--base btn--danger deleteBtn" data-id="{{ $item->id }}"><i class="las la-trash-alt"></i></button>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 5])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $categories->links() }}
    </div>

    {{-- Add Modal --}}
    <div id="add-category" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Add New Category") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.bill.payment.category.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row mb-10-none">
                        <div class="col-xl-12 col-lg-12 form-group">
                            <label>{{ __("Service") }}*</label>
                            <select name="vas_service_id" class="form--control nice-select">
                                <option disabled selected>{{ __("Select Service") }}</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input', [
                                'label'         => __("Name"),
                                'name'          => 'name',
                                'value'         => old('name'),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input', [
                                'label'         => __("Identifier (SafeHaven ID)"),
                                'name'          => 'identifier',
                                'value'         => old('identifier'),
                            ])
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group">
                            <button type="submit" class="btn btn--base w-100">{{ __("Add Category") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="edit-category" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Edit Category") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.bill.payment.category.update',1) }}" enctype="multipart/form-data">
                    @csrf
                    @method("PUT")
                    <div class="row mb-10-none">
                        <div class="col-xl-12 col-lg-12 form-group">
                            <label>{{ __("Service") }}*</label>
                            <select name="vas_service_id" class="form--control nice-select">
                                <option disabled selected>{{ __("Select Service") }}</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input', [
                                'label'         => __("Name"),
                                'name'          => 'name',
                                'value'         => old('name'),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input', [
                                'label'         => __("Identifier (SafeHaven ID)"),
                                'name'          => 'identifier',
                                'value'         => old('identifier'),
                            ])
                        </div>

                        <div class="col-xl-12 col-lg-12 form-group">
                            <button type="submit" class="btn btn--base w-100">{{ __("Update Category") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        openModalWhenError("add-category", "#add-category");
        openModalWhenError("edit-category", "#edit-category");

        $(".addBtn").click(function() {
            openModalBySelector("#add-category");
        });

        $(".editBtn").click(function() {
            var oldData = JSON.parse($(this).attr("data-item"));
            var editModal = $("#edit-category");

            // Use a placeholder ID '1' and replace it with the actual ID
            var actionRoute = "{{ setRoute('admin.bill.payment.category.update', '1') }}";
            actionRoute = actionRoute.replace('1', oldData.id);

            editModal.find("form").attr("action", actionRoute);
            editModal.find("input[name=name]").val(oldData.name);
            editModal.find("input[name=identifier]").val(oldData.identifier);
            editModal.find("select[name=vas_service_id]").val(oldData.vas_service_id).niceSelect('update');

            openModalBySelector("#edit-category");
        });

        $(".deleteBtn").click(function() {
            var alert = confirm("Are you sure to delete this category?");
            if (alert) {
                // Use a placeholder ID '1' and replace it with the actual ID
                var actionRoute = "{{ setRoute('admin.bill.payment.category.delete', '1') }}";
                actionRoute = actionRoute.replace('1', $(this).data("id"));
                
                $.post(actionRoute, {
                    _token: "{{ csrf_token() }}",
                    _method: "DELETE",
                }, function(response) {
                    location.reload();
                }).fail(function(response) {
                    alert("Something went wrong");
                });
            }
        });

        $(document).ready(function(){
            // Switcher
            $('.switcher').change(function() {
                var status = $(this).prop('checked') == true ? 1 : 0;
                var id = $(this).data('id');
                // Use a placeholder ID '1' and replace it with the actual ID
                var actionRoute = "{{ setRoute('admin.bill.payment.category.status', '1') }}";
                actionRoute = actionRoute.replace('1', id);

                $.ajax({
                    type: "PUT",
                    dataType: "json",
                    url: actionRoute,
                    data: {
                        'status': status,
                        '_token': "{{ csrf_token() }}"
                    },
                    success: function(data) {
                        // success message
                    }
                });
            });
        });
    </script>
@endpush
