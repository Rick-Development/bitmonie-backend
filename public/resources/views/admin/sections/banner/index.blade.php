@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Banner Management")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                @include('admin.components.link.add-default',[
                    'text'          => __("Add Banner"),
                    'href'          => "#banner-add",
                    'class'         => "modal-btn",
                    'permission'    => "admin.banner.store", 
                ])
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("Image") }}</th>
                            <th>{{ __("Type") }}</th>
                            <th>{{ __("Link") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Created At") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $item)
                            <tr data-item="{{ json_encode($item) }}">
                                <td>
                                    <ul class="user-list">
                                        <li><img src="{{ asset($item->image) }}" alt="banner"></li>
                                    </ul>
                                </td>
                                <td>{{ ucwords(str_replace('_', ' ', $item->type)) }}</td>
                                <td><a href="{{ $item->link }}" target="_blank">{{ Str::limit($item->link, 30) }}</a></td>
                                <td>
                                    @include('admin.components.form.switcher',[
                                        'name'          => 'status',
                                        'value'         => $item->status,
                                        'options'       => [__('Enable') => 1,__('Disable') => 0],
                                        'onload'        => true,
                                        'data_target'   => $item->uuid,
                                        'permission'    => "admin.banner.status.update",
                                    ])
                                </td>
                                <td>{{ $item->created_at->format('d-m-Y H:i') }}</td>
                                <td>
                                    <button class="btn btn--base edit-modal-button"><i class="las la-pencil-alt"></i></button>
                                    <button class="btn btn--base btn--danger delete-modal-button"><i class="las la-trash-alt"></i></button>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ get_paginate($banners) }}
        </div>
    </div>

    {{-- Add Modal --}}
    @if (admin_permission_by_name("admin.banner.store"))
        <div id="banner-add" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Add Banner") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" method="POST" action="{{ setRoute('admin.banner.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-10-none">
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.form.input-file',[
                                    'label'             => __("Image"),
                                    'name'              => "image",
                                    'class'             => "file-holder",
                                    'old_files_path'    => files_asset_path("default"),
                                    'old_files'         => "default.png",
                                ])
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("Type") }}*</label>
                                <select class="form--control" name="type">
                                    <option value="dashboard">{{ __("Dashboard") }}</option>
                                    <option value="home">{{ __("Home") }}</option>
                                    <option value="p2p">{{ __("P2P") }}</option>
                                    <option value="gift_card">{{ __("Gift Card") }}</option>
                                </select>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => __("Link (Optional)"),
                                    'name'          => "link",
                                    'placeholder'   => "https://...",
                                    'value'         => old("link"),
                                ])
                            </div>

                            <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                                <button type="button" class="btn btn--danger modal-close">{{ __("Close") }}</button>
                                <button type="submit" class="btn btn--base">{{ __("Add") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit Modal --}}
    @if (admin_permission_by_name("admin.banner.update"))
        <div id="banner-edit" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Edit Banner") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" method="POST" action="{{ setRoute('admin.banner.update') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="target" value="">
                        <div class="row mb-10-none">
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.form.input-file',[
                                    'label'             => __("Image"),
                                    'name'              => "image",
                                    'class'             => "file-holder",
                                    'old_files_path'    => files_asset_path("default"),
                                    'old_files'         => "default.png",
                                ])
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("Type") }}*</label>
                                <select class="form--control" name="type">
                                    <option value="dashboard">{{ __("Dashboard") }}</option>
                                    <option value="home">{{ __("Home") }}</option>
                                    <option value="p2p">{{ __("P2P") }}</option>
                                    <option value="gift_card">{{ __("Gift Card") }}</option>
                                </select>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => __("Link (Optional)"),
                                    'name'          => "link",
                                    'placeholder'   => "https://...",
                                    'value'         => old("link"),
                                ])
                            </div>

                            <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                                <button type="button" class="btn btn--danger modal-close">{{ __("Close") }}</button>
                                <button type="submit" class="btn btn--base">{{ __("Update") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('script')
    <script>
        $(document).ready(function(){
            openModalWhenError('banner-add','#banner-add');
            openModalWhenError('banner-edit','#banner-edit');
        });

        $(".edit-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var editModal = $("#banner-edit");

            editModal.find("input[name=target]").val(oldData.uuid);
            editModal.find("input[name=link]").val(oldData.link);
            editModal.find("select[name=type]").val(oldData.type);
            
            // Image handling might be tricky with this component, usually assumes a specific path.
            // For now, we just rely on new upload replacing old.
            
            openModalBySelector("#banner-edit");
        });

        $(".delete-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var actionRoute =  "{{ setRoute('admin.banner.delete') }}";
            var target      = oldData.uuid;
            var message     = `{{ __("Are you sure to delete this banner?") }}`;
            openDeleteModal(actionRoute,target,message);
        });

        switcherAjax("{{ setRoute('admin.banner.status.update') }}");
    </script>
@endpush
