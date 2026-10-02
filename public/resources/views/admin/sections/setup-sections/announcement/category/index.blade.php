@php
    $app_local = get_default_language_code();
@endphp
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
    ], 'active' => __("Setup Section")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Categories") }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.link.add-default',[
                        'text'          => "Add Category",
                        'href'          => "#category-add",
                        'class'         => "modal-btn",
                        'permission'    => "admin.setup.sections.announcement.category.store",
                    ])
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Created At") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories ?? [] as $item)
                            <tr data-item="{{ json_encode($item) }}">
                                <td>{{ $item->name?->language?->$app_local?->name ?? null}}</td>
                                <td>
                                    @include('admin.components.form.switcher',[
                                        'name'          => 'status',
                                        'value'         => $item->status,
                                        'options'       => [__('Active') => 1,__('Deactive') => 0],
                                        'onload'        => true,
                                        'data_target'   => $item->id,
                                        'permission'    => "admin.setup.sections.announcement.category.status.update",
                                    ])
                                </td>
                                <td>{{ $item->created_at->format("d-m-y h:i:s") }}</td>
                                <td>
                                    @include('admin.components.link.edit-default',[
                                        'href'          => "javascript:void(0)",
                                        'class'         => "edit-modal-button",
                                        'permission'    => "admin.setup.sections.announcement.category.update",
                                    ])
                                    @include('admin.components.link.delete-default',[
                                        'href'          => "javascript:void(0)",
                                        'class'         => "delete-modal-button",
                                        'permission'    => "admin.setup.sections.announcement.category.delete",
                                    ])
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 7])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Store --}}
    @if (admin_permission_by_name("admin.setup.sections.announcement.category.store"))
        <div id="category-add" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Add Category") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" method="POST" action="{{ setRoute('admin.setup.sections.announcement.category.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-10-none">
                            <div class="col-xl-12 col-lg-12">
                                <div class="product-tab">
                                    <nav>
                                        <div class="nav nav-tabs" id="nav-tab" role="tablist">
                                            @foreach ($languages as $item)
                                                <button class="nav-link @if (get_default_language_code() == $item->code) active @endif" id="{{$item->name}}-tab" data-bs-toggle="tab" data-bs-target="#{{$item->name}}" type="button" role="tab" aria-controls="{{ $item->name }}" aria-selected="true">{{ $item->name }}</button>
                                            @endforeach
                                        </div>
                                    </nav>
                                    <div class="tab-content" id="nav-tabContent">
                                        @foreach ($languages as $item)
                                            @php
                                                $lang_code = $item->code;
                                            @endphp
                                            <div class="tab-pane @if (get_default_language_code() == $item->code) fade show active @endif" id="{{ $item->name }}" role="tabpanel" aria-labelledby="english-tab">
                                                <div class="col-xl-12 col-lg-12 form-group">
                                                    @include('admin.components.form.input',[
                                                        'label'         => __('Name'),
                                                        'label_after'   => '*',
                                                        'name'          => $item->code . "_name",
                                                        'value'         => old($item->code . "_name")
                                                    ])
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                                <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
                                <button type="submit" class="btn btn--base">{{ __("Add") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Update --}}
    @if (admin_permission_by_name("admin.setup.sections.announcement.category.update"))
        <div id="category-update" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Update Category") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" method="POST" action="{{ setRoute('admin.setup.sections.announcement.category.update') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="target" value="{{ old('target') }}">
                        <div class="row mb-10-none">
                            <div class="col-xl-12 col-lg-12">
                                <div class="product-tab">
                                    <nav>
                                        <div class="nav nav-tabs" id="nav-tab" role="tablist">
                                            @foreach ($languages as $item)
                                                <button class="nav-link @if (get_default_language_code() == $item->code) active @endif" id="{{$item->name}}-tab" data-bs-toggle="tab" data-bs-target="#{{$item->name}}" type="button" role="tab" aria-controls="{{ $item->name }}" aria-selected="true">{{ $item->name }}</button>
                                            @endforeach
                                        </div>
                                    </nav>
                                    <div class="tab-content" id="nav-tabContent">
                                        @foreach ($languages as $item)
                                            @php
                                                $lang_code = $item->code;
                                            @endphp
                                            <div class="tab-pane @if (get_default_language_code() == $item->code) fade show active @endif" id="{{ $item->name }}" role="tabpanel" aria-labelledby="english-tab">
                                                <div class="col-xl-12 col-lg-12 form-group">
                                                    @include('admin.components.form.input',[
                                                        'label'         => __('Name'),
                                                        'label_after'   => '*',
                                                        'name'          => $item->code . "_name_edit",
                                                        'value'         => old($item->code . "_name_edit")
                                                    ])
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                                <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
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

        var languages = "{{ $__languages }}";
        languages = JSON.parse(languages.replace(/&quot;/g,'"'));
        var appLocal = "{{ $app_local }}";

        $(document).ready(function(){
            // Switcher
            switcherAjax("{{ setRoute('admin.setup.sections.announcement.category.status.update') }}");

            openModalWhenError("category-update","#category-update");
        })

        $(".delete-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));

            var actionRoute =  "{{ setRoute('admin.setup.sections.announcement.category.delete') }}";
            var target      = oldData.id;
            var message     = `{{ __("Are you sure to delete") }} <strong>${oldData?.name?.language[appLocal]?.name}</strong> {{ __('category?') }}`;

            openDeleteModal(actionRoute,target,message);
        });

        $(document).on("click",".edit-modal-button",function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var editModal = $("#category-update");

            editModal.find(".invalid-feedback").remove();
            editModal.find(".form--control").removeClass("is-invalid");

            editModal.find("form").first().find("input[name=target]").val(oldData.id);

            $.each(languages,function(index,item) {
                editModal.find("input[name="+item.code+"_name_edit]").val(oldData?.name.language[item.code]?.name);
            });

            openModalBySelector("#category-update");
        });
    </script>
@endpush