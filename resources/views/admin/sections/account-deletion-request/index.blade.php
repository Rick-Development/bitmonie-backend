@extends('admin.layouts.master')

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
        'active' => __('Account Deletion Requests'),
    ])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="title">
                    {{ __($page_title) }}
                    <span class="badge badge--success">{{ $requests->total() }}</span>
                </h5>

                <form method="GET" action="{{ setRoute('admin.account.deletion.requests.index') }}" class="d-flex flex-wrap gap-2">
                    <select name="status" class="form--control">
                        <option value="">{{ __('All Statuses') }}</option>
                        @foreach (\App\Models\AccountDeletionRequest::STATUSES as $item)
                            <option value="{{ $item }}" @selected($status === $item)>{{ __(ucfirst($item)) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="search" value="{{ $search }}" class="form--control" placeholder="{{ __('Search name, email, phone, reference') }}">
                    <button type="submit" class="btn--base">{{ __('Filter') }}</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $key => $item)
                            <tr data-item="{{ json_encode($item->only(['id', 'reference_id', 'full_name', 'email', 'phone_number', 'reason', 'status', 'created_at'])) }}">
                                <td>{{ $key + $requests->firstItem() }}</td>
                                <td>{{ $item->reference_id }}</td>
                                <td>{{ $item->full_name }}</td>
                                <td>{{ $item->email }}</td>
                                <td>{{ $item->phone_number }}</td>
                                <td>
                                    @php
                                        $badge = match($item->status) {
                                            'completed' => 'success',
                                            'processing' => 'info',
                                            'rejected' => 'danger',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <span class="badge badge--{{ $badge }}">{{ __(ucfirst($item->status)) }}</span>
                                </td>
                                <td>{{ $item->created_at->format('d-m-Y H:i:s') }}</td>
                                <td>
                                    <a href="#details" class="btn btn--base details-button modal-btn">
                                        <i class="las la-info-circle"></i>
                                    </a>
                                    <a href="#status-update" class="btn btn--base status-button modal-btn">
                                        <i class="las la-sync"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty', ['colspan' => 8])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ get_paginate($requests) }}
    </div>

    <div id="details" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __('Account Deletion Request') }}</h5>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>

    <div id="status-update" class="mfp-hide">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __('Update Status') }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" action="{{ setRoute('admin.account.deletion.requests.status.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="target">
                    <div class="row mb-10-none">
                        <div class="col-xl-12 col-lg-12 form-group">
                            <label>{{ __('Status') }}</label>
                            <select name="status" class="form--control">
                                @foreach (\App\Models\AccountDeletionRequest::STATUSES as $item)
                                    <option value="{{ $item }}">{{ __(ucfirst($item)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.button.form-btn', [
                                'class' => 'w-100 btn-loading',
                                'type' => 'submit',
                                'text' => __('Update Status'),
                            ])
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(".details-button").click(function () {
            const request = JSON.parse($(this).parents("tr").attr("data-item"));
            const reason = request.reason ? request.reason : "{{ __('Not provided') }}";

            const htmlMarkup = `
                <div class="user-info">
                    <ul class="border-bottom pb-3">
                        <li><strong>{{ __('Reference') }}: </strong><span>${request.reference_id}</span></li>
                        <li><strong>{{ __('Full Name') }}: </strong><span>${request.full_name}</span></li>
                        <li><strong>{{ __('Email') }}: </strong><span>${request.email}</span></li>
                        <li><strong>{{ __('Phone') }}: </strong><span>${request.phone_number}</span></li>
                        <li><strong>{{ __('Status') }}: </strong><span>${request.status}</span></li>
                        <li><strong>{{ __('Submitted At') }}: </strong><span>${request.created_at}</span></li>
                    </ul>
                </div>
                <div class="message">
                    <h4 class="mt-3">{{ __('Reason') }}</h4>
                    <p>${reason}</p>
                </div>
            `;

            $("#details").find(".modal-body").html(htmlMarkup);
        });

        $(".status-button").click(function () {
            const request = JSON.parse($(this).parents("tr").attr("data-item"));
            $("#status-update").find("input[name=target]").val(request.id);
            $("#status-update").find("select[name=status]").val(request.status);
        });
    </script>
@endpush
