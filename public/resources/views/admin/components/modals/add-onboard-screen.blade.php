@if (admin_permission_by_name("admin.app.settings.onboard.screen.store"))
    <div id="onboard-screen-add" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header">
                <h5 class="modal-title">{{ __("Add New Screen") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.app.settings.onboard.screen.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row mb-10-none">
                        <div class="card-body">
                            <div class="row mb-10-none">
                                <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 form-group">
                                    @include('admin.components.form.input-file',[
                                        'label'             => __('Image').': <span class="text--danger">(375*812)</span>'.'*',
                                        'class'             => "file-holder",
                                        'name'              => "image",
                                    ])
                                </div>
                                <div class="col-xl-8 col-lg-8 col-md-6 col-sm-12 form-group">
                                    <div class="row mb-10-none mt-3">
                                        <div class="language-tab">
                                            <nav>
                                                <div class="nav nav-tabs" id="nav-tab" role="tablist">
                                                    @foreach ($languages as $item)
                                                        <button class="nav-link @if (get_default_language_code() == $item->code) active @endif" id="modal-{{$item->name}}-tab" data-bs-toggle="tab" data-bs-target="#modal-{{$item->name}}" type="button" role="tab" aria-controls="modal-{{ $item->name }}" aria-selected="true">{{ $item->name }}</button>
                                                    @endforeach
                                                </div>
                                            </nav>
                                            <div class="tab-content" id="nav-tabContent">
                                                @foreach ($languages as $item)
                                                    @php
                                                        $lang_code = $item->code;
                                                    @endphp
                                                    <div class="tab-pane @if (get_default_language_code() == $item->code) fade show active @endif" id="modal-{{ $item->name }}" role="tabpanel" aria-labelledby="modal-{{$item->name}}-tab">
                                                        <div class="form-group">
                                                            @include('admin.components.form.input',[
                                                                'label'     => __("Heading").'*',
                                                                'name'      => $lang_code ."_heading",
                                                                'value'     => old($lang_code.'_heading'),
                                                                'placeholder'   => __( "Write Here.."),
                                                            ])
                                                        </div>
                                                        <div class="form-group">
                                                            @include('admin.components.form.input',[
                                                                'label'     => __("Title").'*',
                                                                'name'      => $lang_code ."_title",
                                                                'value'     => old($lang_code.'_title'),
                                                                'placeholder'   => __( "Write Here.."),
                                                            ])
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            @include('admin.components.form.input',[
                                                                'label'     => __("Details").'*',
                                                                'name'      => $lang_code ."_details",
                                                                'value'     => old($lang_code.'_details'),
                                                                'placeholder'   => __( "Write Here.."),
                                                            ])
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
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

    @push('script')
        <script>
            openModalWhenError("onboard-screen-add","#onboard-screen-add");
        </script>
    @endpush
@endif