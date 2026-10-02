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
                <h5 class="title">{{ __("Announcements") }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.link.add-default',[
                        'text'          => "Add Announcement",
                        'href'          => setRoute('admin.setup.sections.announcement.create'),
                        'class'         => "modal-btn",
                        'permission'    => "admin.setup.sections.announcement.create",
                    ])
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>{{ __("Title") }}</th>
                            <th>{{ __("Description") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Created At") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($announcements ?? [] as $item)
                            @php
                                $description = $item->data?->language?->$app_local->description ?? null;
                                $description = strip_tags($description);
                            @endphp
                            <tr data-item="{{ json_encode($item->only(['id'])) }}">
                                <td>
                                    <ul class="user-list">
                                        <li><img src="{{ get_image($item->data?->image ?? null,'site-section') }}" alt="image"></li>
                                    </ul>
                                </td>
                                <td>{{ Str::words($item->data?->language?->$app_local->title ?? null, 3, '...') }}</td>
                                <td>{{ Str::words($description, 10, '...') }}</td>
                                <td>
                                    @include('admin.components.form.switcher',[
                                        'name'          => 'status',
                                        'value'         => $item->status,
                                        'options'       => [__('Active') => 1,__('Deactive') => 0],
                                        'onload'        => true,
                                        'data_target'   => $item->id,
                                        'permission'    => "admin.setup.sections.announcement.status.update",
                                    ])
                                </td>
                                <td>{{ $item->created_at->format("d-m-y h:i:s") }}</td>
                                <td>
                                    @include('admin.components.link.edit-default',[
                                        'href'          => setRoute('admin.setup.sections.announcement.edit',$item->id),
                                        'class'         => "edit-modal-button",
                                        'permission'    => "admin.setup.sections.announcement.edit",
                                    ])
                                    @include('admin.components.link.delete-default',[
                                        'href'          => "javascript:void(0)",
                                        'class'         => "delete-modal-button",
                                        'permission'    => "admin.setup.sections.announcement.delete",
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

@endsection

@push('script')
    <script>

        $(document).ready(function(){
            // Switcher
            switcherAjax("{{ setRoute('admin.setup.sections.announcement.status.update') }}");
        })

        $(".delete-modal-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));

            var actionRoute =  "{{ setRoute('admin.setup.sections.announcement.delete') }}";
            var target      = oldData.id;
            var message     = `{{ __("Are you sure to delete this announcement?") }}`;

            openDeleteModal(actionRoute,target,message);
        });
    </script>
@endpush