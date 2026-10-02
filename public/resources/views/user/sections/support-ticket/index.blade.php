@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="support-tickets">
    <div class="table-area mt-10">
        <div class="table-wrapper">
            <div class="dashboard-header-wrapper">
                <h4 class="title">{{ __($page_title) }}</h4>
                <div class="dashboard-btn-wrapper">
                    <div class="dashboard-btn">
                        <a href="{{ route('user.support.ticket.create') }}" class="btn--base"><i class="las la-plus me-1"></i> {{ __('Add New') }}</a>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('Ticket ID') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Message') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{__('Last Reply')}}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($support_tickets as $item)
                            <tr>
                                <td>#{{ $item->token }}</td>
                                <td><span class="text--info">{{ $item->subject }}</span></td>
                                <td>{{ Str::words($item->desc, 10, '...') }}</td>
                                <td>
                                    <span class="{{ $item->stringStatus->class }}">{{ $item->stringStatus->value }}</span>
                                </td>
                                <td>{{ $item->created_at->format("Y-m-d H:i A") }}</td>
                                <td>
                                    <a href="{{ route('user.support.ticket.conversation',encrypt($item->id)) }}" class="btn btn--base"><i class="las la-comment"></i></a>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
            <nav>
                {{ get_paginate($support_tickets) }}
            </nav>
        </div>
     </div>
</div>

@endsection

@push('script')
    <script>

    </script>
@endpush
