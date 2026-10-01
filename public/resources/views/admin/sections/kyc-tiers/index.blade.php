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
    ], 'active' => __("KYC Tiers")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
            </div>
            <div class="table-responsive">
                <table class="custom-table bank-search-table">
                    <thead>
                        <tr>
                            <th>{{ __("Level") }}</th>
                            <th>{{ __("Name") }}</th>
                            <th>{{ __("VForm ID") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tiers as $item)
                            <tr data-item="{{ json_encode($item) }}">
                                <td>{{ $item->level }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->vform_id ?? "N/A" }}</td>
                                <td>
                                    @include('admin.components.form.switcher',[
                                        'label'         => false,
                                        'name'          => 'status',
                                        'options'       => [__('Active') => 1 , __('Deactive') => 0],
                                        'onload'        => true,
                                        'value'         => $item->status,
                                        'data_target'   => $item->id,
                                        'permission'    => "admin.kyc.tiers.status.update",   
                                    ])
                                </td>
                                <td>
                                    <button class="btn btn--base edit-modal-button"><i class="las la-pencil-alt"></i></button>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 5])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="edit-tier-modal" class="mfp-hide white-popup">
        <div class="card my-5">
            <div class="card-header bg--base">
                <h4 class="text-white">{{ __("Edit KYC Tier") }}</h4>
            </div>
            <div class="card-body">
                <form class="card-form" action="{{ setRoute('admin.kyc.tiers.update') }}" method="POST">
                    @csrf
                    @method("PUT")
                    <input type="hidden" name="target" value="">
                    <div class="row">
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("Name")."*",
                                'name'          => "name",
                                'placeholder'   => __("Tier Name"),
                                'value'         => old('name'),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.textarea',[
                                'label'         => __("Description"),
                                'name'          => "description",
                                'placeholder'   => __("Description"),
                                'value'         => old('description'),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.textarea',[
                                'label'         => __("Requirements"),
                                'name'          => "requirements",
                                'placeholder'   => __("Requirements (e.g. BVN, Selfie)"),
                                'value'         => old('requirements'),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __("YouVerify VForm ID"),
                                'name'          => "vform_id",
                                'placeholder'   => __("VForm ID from YouVerify Dashboard"),
                                'value'         => old('vform_id'),
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __("Status"),
                                'name'          => "status",
                                'value'         => old('status', 1),
                                'options'       => [__('Active') => 1 , __('Deactive') => 0],
                            ])
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12">
                        <button type="submit" class="btn--base w-100">{{ __("Update") }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function(){
            switcherAjax("{{ setRoute('admin.kyc.tiers.status.update') }}");

            $(".edit-modal-button").click(function(){
                var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
                var modal = $("#edit-tier-modal");

                modal.find("input[name=target]").val(oldData.id);
                modal.find("input[name=name]").val(oldData.name);
                modal.find("textarea[name=description]").val(oldData.description);
                modal.find("textarea[name=requirements]").val(oldData.requirements);
                modal.find("input[name=vform_id]").val(oldData.vform_id);
                
                modal.find("input[name=status]").val(oldData.status);
                if(oldData.status == 1) {
                    modal.find(".switchery").attr("style","background-color: rgb(0, 149, 131); border-color: rgb(0, 149, 131); box-shadow: rgb(0, 149, 131) 0px 0px 0px 16px inset; transition: border 0.4s ease 0s, box-shadow 0.4s ease 0s, background-color 1.2s ease 0s;");
                    modal.find(".switchery > small").attr("style","left: 20px; transition: background-color 0.4s ease 0s, left 0.2s ease 0s; background-color: rgb(255, 255, 255);");
                }else {
                    modal.find(".switchery").attr("style","box-shadow: rgb(223, 223, 223) 0px 0px 0px 0px inset; border-color: rgb(223, 223, 223); background-color: rgb(255, 255, 255); transition: border 0.4s ease 0s, box-shadow 0.4s ease 0s;");
                    modal.find(".switchery > small").attr("style","left: 0px; transition: background-color 0.4s ease 0s, left 0.2s ease 0s;");
                }

                $.magnificPopup.open({
                    items: {
                        src: "#edit-tier-modal",
                        type: "inline",
                    }
                });
            });
        });
    </script>
@endpush
