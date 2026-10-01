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
        'active' => __('Subscribers'),
    ])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                <div class="table-btn-area">

                    @include('admin.components.link.add-default',[
                        'text'          => __("Send Mail to All"),
                        'href'          => "#send-mail-subscribers",
                        'class'         => "modal-btn",
                        'permission'    => "admin.subscriber.send.mail",
                    ])
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>{{ __("Email") }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscribers ?? [] as $key => $item)
                            <tr>
                                <td>{{ $key + $subscribers->firstItem() }}</td>
                                <td>{{ $item->email }}</td>
                                <td>{{ $item->created_at->format("d-m-Y H:i:s") }}</td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 3])
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
        {{ get_paginate($subscribers) }}
    </div>

    {{-- Send Mail Modal --}}
    @include('admin.components.modals.subsciber-send-mail')
@endsection

@push('script')
    <script>
        openModalWhenError("send-mail-subscribers","#send-mail-subscribers");
    </script>
@endpush
