@php
    $default_lang_code = language_const()::NOT_REMOVABLE;
    $system_default_lang = get_default_language_code();
    $languages_for_js_use = $languages->toJson();
@endphp

@extends('admin.layouts.master')

@push('css')
    <link rel="stylesheet" href="{{ asset('backend/css/fontawesome-iconpicker.min.css') }}">
    <style>
        .fileholder {
            min-height: 374px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 330px !important;
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
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __($page_title) }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form" action="{{ setRoute('admin.setup.sections.section.update',$slug) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row justify-content-center mb-10-none">
                    <div class="col-xl-4 col-lg-4 form-group">
                        @include('admin.components.form.input-file',[
                            'label'             => __("Image"),
                            'name'              => "image",
                            'class'             => "file-holder",
                            'old_files_path'    => files_asset_path("site-section"),
                            'old_files'         => $data->value->image ?? "",
                        ])
                    </div>
                    <div class="col-xl-8 col-lg-8">
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
                                        <div class="form-group">
                                            @include('admin.components.form.input',[
                                                'label'         => __("Heading"),
                                                'label_after'   => "*",
                                                'name'          => $item->code . "_heading",
                                                'value'         => old($item->code . "_heading",$data->value->language->$lang_code->heading ?? "")
                                            ])
                                        </div>
                                        <div class="form-group">
                                            @include('admin.components.form.textarea',[
                                                'label'         => __("Sub Heading"),
                                                'label_after'   => "*",
                                                'name'          => $item->code . "_sub_heading",
                                                'value'         => old($item->code . "_sub_heading",$data->value->language->$lang_code->sub_heading ?? "")
                                            ])
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        @include('admin.components.button.form-btn',[
                            'class'         => "w-100 btn-loading",
                            'text'          => "Update",
                            'permission'    => "admin.setup.sections.section.update"
                        ])
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="table-area mt-15">
        <div class="table-wrapper">
            <div class="table-header justify-content-end">
                <div class="table-btn-area">
                    <a href="#overview-add" class="btn--base modal-btn"><i class="fas fa-plus me-1"></i> {{ __("Add Item") }}</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("Title") }}</th>
                            <th>{{ __("Description") }}</th>
                            <th>{{ __("Counter Value") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data->value->items ?? [] as $key => $item)
                            <tr data-item="{{ json_encode($item) }}">
                                <td><span class="text--dark">{{ $item->language->$system_default_lang->title ?? "" }}</span></td>
                                <td><span class="text--dark">{{ $item->language->$system_default_lang->description ?? "" }}</span></td>
                                <td><span class="text--dark">{{ formatNumberInKNotation($item->counter_value) ?? "" }}</span></td>
                                <td>
                                    <button class="btn btn--base edit-modal-button"><i class="las la-pencil-alt"></i></button>
                                    <button class="btn btn--base btn--danger delete-modal-button" ><i class="las la-trash-alt"></i></button>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 4])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.components.modals.site-section.overview-section.add')
    @include('admin.components.modals.site-section.overview-section.edit')
    

@endsection

@push('script')
    <script src="{{ asset('backend/js/fontawesome-iconpicker.js') }}"></script>

    <script>
        // icon picker
        $('.icp-auto').iconpicker();
    </script>

    <script>
        openModalWhenError("overview-add","#overview-add");
        openModalWhenError("overview-edit","#overview-edit");

        var default_language = "{{ $default_lang_code }}";
        var system_default_language = "{{ $system_default_lang }}";
        var languages = "{{ $languages_for_js_use }}";
        languages = JSON.parse(languages.replace(/&quot;/g,'"'));

        $(".edit-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            var editModal = $("#overview-edit");

            editModal.find("form").first().find("input[name=target]").val(oldData.id);

            $.each(languages,function(index,item) {
                editModal.find("input[name="+item.code+"_title_edit]").val(oldData.language[item.code]?.title);
                editModal.find("textarea[name="+item.code+"_description_edit]").val(oldData.language[item.code]?.description);
                editModal.find("input[name=counter_value_edit]").val(oldData.counter_value);
            });
            openModalBySelector("#overview-edit");

        });

        $(".delete-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));

            var actionRoute =  "{{ setRoute('admin.setup.sections.section.item.delete',$slug) }}";
            var target = oldData.id;
            var message     = `{{ __("Are you sure to") }} <strong>delete</strong> {{ __("item?") }}`;

            openDeleteModal(actionRoute,target,message);
        }); 
    </script>
@endpush