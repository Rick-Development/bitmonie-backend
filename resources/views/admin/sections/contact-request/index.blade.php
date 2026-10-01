@extends('admin.layouts.master')

@push('css')
@endpush

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
        ],
        'active' => __('Contact Messages'),
    ])
@endsection
@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <form method="POST" action="{{ setRoute('admin.contact.messages.delete.all') }}">
                @csrf
                <div class="table-header">
                    <h5 class="title">{{ __($page_title) }}  <span class="badge badge--success">{{ $contact_requests->total() }}</span> </h5>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn--base bg--danger mark-delete-btn">{{ __("Mark Delete") }}</button>
                        <a href="{{ setRoute('admin.contact.messages.export') }}" class="btn--base bg--success">{{ __("Export (Excel)") }}</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" class="form--control mark-all-checkbox" data-parent=".mark-all-parent" data-child=".mark-all-child">
                                </th>
                                <th></th>
                                <th>{{ __("Name") }}</th>
                                <th>{{ __("Email") }}</th>
                                <th>{{ __("Reply") }}</th>
                                <th>{{ __("Created At") }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="mark-all-parent">
                            @forelse ($contact_requests ?? [] as $key => $item)
                                <tr data-item="{{ json_encode($item->only(['id','message','name','email','created_at'])) }}">
                                    <td>
                                        <input type="checkbox" value="{{ $item->id }}" name="mark[]" class="form--control mark-all-child">
                                    </td>
                                    <td>{{ $key + $contact_requests->firstItem() }}</td>
                                    <td>{{ $item->name ?? '' }}</td>
                                    <td>{{ $item->email ?? '' }}</td>
                                    <td>
                                        @if ($item->reply == true)
                                            <span class="badge badge--success">{{ __("Replyed") }}</span>
                                        @else
                                            <span class="badge badge--warning">{{ __("Not Replyed") }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->created_at->format("d-m-Y H:i:s") }}</td>
                                    <td>
                                        @include('admin.components.link.custom',[
                                            'href'          => "#send-reply",
                                            'class'         => "btn btn--base reply-button modal-btn",
                                            'icon'          => "las la-envelope-open-text",
                                            'permission'    => "admin.contact.messages.reply",
                                        ])
                                        @include('admin.components.link.custom',[
                                            'href'          => "#details",
                                            'class'         => "btn btn--base details-button modal-btn",
                                            'icon'          => "las la-info-circle",
                                            'permission'    => "admin.contact.messages.index",
                                        ])
                                        @include('admin.components.link.custom',[
                                            'href'          => "javascript:void(0)",
                                            'class'         => "btn btn--base bg--danger delete-button",
                                            'icon'          => "las la-trash",
                                            'permission'    => "admin.contact.messages.delete",
                                        ])
                                    </td>
                                </tr>
                            @empty
                                @include('admin.components.alerts.empty',['colspan' => 7])
                            @endforelse
                        </tbody>
                    </table>

                </div>
            </form>
        </div>
        {{ get_paginate($contact_requests) }}
    </div>

    {{-- Send Mail Modal --}}
    @if (admin_permission_by_name("admin.contact.messages.reply"))
        <div id="send-reply" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Send Reply") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="modal-form" action="{{ setRoute('admin.contact.messages.reply') }}" method="POST">
                        @csrf
                        <input type="hidden" name="target" value="{{ old('target') }}">
                        <div class="row mb-10-none">
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.form.input',[
                                    'label'         => __("Subject"),
                                    'label_after'   => "*",
                                    'name'          => "subject",
                                    'data_limit'    => 150,
                                    'placeholder'   => __("Write Here").'...',
                                    'value'         => old('subject'),
                                ])
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.form.input-text-rich',[
                                    'label'         => __("Details"),
                                    'label_after'   => "*",
                                    'name'          => "message",
                                    'value'         => old('message'),
                                ])
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                @include('admin.components.button.form-btn',[
                                    'class'         => "w-100 btn-loading",
                                    'type'          => "submit",
                                    'text'          => __("Send Email"),
                                ])
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif


     {{-- Message Detials Modal --}}
     @if (admin_permission_by_name("admin.contact.messages.index"))
     <div id="details" class="mfp-hide large">
         <div class="modal-data">
             <div class="modal-header px-0">
                 <h5 class="modal-title">{{ __("Contact Message") }}</h5>
             </div>
             <div class="modal-body">

                 {{-- data will be place from javascript --}}

             </div>
         </div>
     </div>
 @endif

@endsection

@push('script')
    <script>
        openModalWhenError("send-reply","#send-reply");

        $(".reply-button").click(function(){
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
            $("#send-reply").find("input[name=target]").val(oldData.id);
        });

        $(".details-button").click(function(){
            let message = JSON.parse($(this).parents("tr").attr("data-item"));

            let htmlMarkup = `
            <div class = "user-info">
                <ul class = "border-bottom pb-3">
                    <li>
                        <strong>Name: </strong> <span>${message.name}</span>
                    </li>
                    <li>
                        <strong>Email: </strong> <span>${message.email}</span>
                    </li>
                    <li>
                        <strong>Send At: </strong> <span>${message.created_at}</span>
                    </li>
                </ul>
            </div>

            <div class="message">

                <h4 class="mt-3">Message</h4>

                <p>
                    ${message.message}
                </p>
            </div>

            `;
            $("#details").find(".modal-body").html(htmlMarkup);
        });

        $(".delete-button").click(function() {
            var oldData = JSON.parse($(this).parents("tr").attr("data-item"));

            var actionRoute =  "{{ setRoute('admin.contact.messages.delete') }}";
            var target      = oldData.id;
            var message     = `{{ __("Are you sure to delete this message?") }}`;

            openDeleteModal(actionRoute,target,message);
        });
        function openDeleteModal(URL,target,message,actionBtnText = "{{ __('Remove') }}",method = "DELETE"){
            if(URL == "" || target == "") {
                return false;
            }

            if(message == "") {
                message = "{{ __('Are you sure to delete ?') }}";
            }
            var method = `<input type="hidden" name="_method" value="${method}">`;
            openModalByContent(
                {
                    content: `<div class="card modal-alert border-0">
                                <div class="card-body">
                                    <form method="POST" action="${URL}">
                                        <input type="hidden" name="_token" value="${laravelCsrf()}">
                                        ${method}
                                        <div class="head mb-3">
                                            ${message}
                                            <input type="hidden" name="target" value="${target}">
                                        </div>
                                        <div class="foot d-flex align-items-center justify-content-between">
                                            <button type="button" class="modal-close btn btn--info">{{ __("Close") }}</button>
                                            <button type="submit" class="alert-submit-btn btn btn--danger btn-loading">${actionBtnText}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>`,
                },

            );
            }
        $(".mark-all-checkbox").change(function() {

            let parentEl = $($(this).attr("data-parent"));
            let childEl = $(parentEl).find($(this).attr("data-child"));

            if($(this).is(":checked")) {
                $.each(childEl, function(index, item) {
                    $(item).prop("checked", true);
                });
            }else {
                $.each(childEl, function(index, item) {
                    $(item).prop("checked", false);
                });
            }
        });
    </script>
@endpush