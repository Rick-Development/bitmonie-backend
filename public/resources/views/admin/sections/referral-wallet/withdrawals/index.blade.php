@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($pageTitle)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Referral Withdrawals")])
@endsection

@section('content')
    @php
        $tabs = [
            'admin.referral.wallet.withdrawals.index' => 'All Requests',
            'admin.referral.wallet.withdrawals.pending' => 'Pending',
            'admin.referral.wallet.withdrawals.completed' => 'Completed',
            'admin.referral.wallet.withdrawals.rejected' => 'Rejected',
        ];
        $currentRoute = Route::currentRouteName();
    @endphp

    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-body">
                <div class="d-flex flex-wrap mb-3">
                    @foreach($tabs as $route => $label)
                        <a href="{{ setRoute($route) }}"
                           class="btn {{ $currentRoute === $route ? 'btn--base' : 'btn--dark' }} mr-2 mb-2">
                            {{ __($label) }}
                        </a>
                    @endforeach
                </div>

                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>@lang('User')</th>
                                <th>@lang('Reference')</th>
                                <th>@lang('Amount')</th>
                                <th>@lang('Bank Details')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Submitted')</th>
                                <th>@lang('Admin Note')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($withdrawals as $withdrawal)
                                <tr>
                                    <td data-label="@lang('User')">
                                        <span class="font-weight-bold">{{ $withdrawal->user->fullname ?? 'N/A' }}</span>
                                        <br>
                                        @if($withdrawal->user)
                                            <a href="{{ setRoute('admin.users.details', $withdrawal->user->username) }}">
                                                <span>@</span>{{ $withdrawal->user->username }}
                                            </a>
                                        @endif
                                    </td>
                                    <td data-label="@lang('Reference')">{{ $withdrawal->reference }}</td>
                                    <td data-label="@lang('Amount')">
                                        <strong>{{ get_amount($withdrawal->amount) }} {{ $withdrawal->currency_code }}</strong>
                                    </td>
                                    <td data-label="@lang('Bank Details')">
                                        <strong>{{ $withdrawal->bank_name }}</strong>
                                        <br>{{ $withdrawal->account_name }}
                                        <br>{{ $withdrawal->account_number }}
                                    </td>
                                    <td data-label="@lang('Status')">
                                        @if($withdrawal->status === 'pending')
                                            <span class="badge badge--warning">@lang('Pending')</span>
                                        @elseif($withdrawal->status === 'completed')
                                            <span class="badge badge--success">@lang('Completed')</span>
                                        @else
                                            <span class="badge badge--danger">@lang('Rejected')</span>
                                        @endif
                                    </td>
                                    <td data-label="@lang('Submitted')">
                                        {{ $withdrawal->created_at?->format('d-m-Y H:i:s') }}
                                        <br>{{ $withdrawal->created_at?->diffForHumans() }}
                                    </td>
                                    <td data-label="@lang('Admin Note')">
                                        @if($withdrawal->admin_note)
                                            {{ $withdrawal->admin_note }}
                                        @else
                                            <span class="text-muted">@lang('No note')</span>
                                        @endif

                                        @if($withdrawal->reviewer)
                                            <br><small class="text-success">@lang('By'): {{ $withdrawal->reviewer->fullname }}</small>
                                        @endif
                                    </td>
                                    <td data-label="@lang('Action')">
                                        @if($withdrawal->status === 'pending')
                                            <button
                                                type="button"
                                                class="btn btn--success btn-sm mb-1 approveBtn"
                                                data-id="{{ $withdrawal->id }}"
                                                data-reference="{{ $withdrawal->reference }}"
                                                data-amount="{{ get_amount($withdrawal->amount) }} {{ $withdrawal->currency_code }}">
                                                @lang('Approve')
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn--danger btn-sm rejectBtn"
                                                data-id="{{ $withdrawal->id }}"
                                                data-reference="{{ $withdrawal->reference }}">
                                                @lang('Reject')
                                            </button>
                                        @else
                                            <span class="text-muted">@lang('Reviewed')</span>
                                            @if($withdrawal->reviewed_at)
                                                <br><small>{{ $withdrawal->reviewed_at->format('d-m-Y H:i:s') }}</small>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-muted" colspan="100%">@lang('No withdrawal requests found')</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($withdrawals->hasPages())
                    <div class="pt-3">
                        {{ get_paginate($withdrawals) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="approveModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Approve Withdrawal')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" class="approve-form">
                    @csrf
                    <div class="modal-body">
                        <p>@lang('Approve withdrawal request') <strong class="approve-reference"></strong>?</p>
                        <p>@lang('Amount') : <strong class="approve-amount"></strong></p>
                        <div class="form-group">
                            <label>@lang('Internal Note') (@lang('Optional'))</label>
                            <textarea name="admin_note" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--success">@lang('Approve')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Reject Withdrawal')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" class="reject-form">
                    @csrf
                    <div class="modal-body">
                        <p>@lang('Reject withdrawal request') <strong class="reject-reference"></strong>?</p>
                        <div class="form-group">
                            <label>@lang('Internal Note')</label>
                            <textarea name="admin_note" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--danger">@lang('Reject')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function ($) {
            "use strict";

            var approveUrl = "{{ route('admin.referral.wallet.withdrawals.approve', ':id') }}";
            var rejectUrl = "{{ route('admin.referral.wallet.withdrawals.reject', ':id') }}";

            $('.approveBtn').on('click', function () {
                var data = $(this).data();
                var modal = $('#approveModal');
                modal.find('.approve-form').attr('action', approveUrl.replace(':id', data.id));
                modal.find('.approve-reference').text(data.reference);
                modal.find('.approve-amount').text(data.amount);
                modal.modal('show');
            });

            $('.rejectBtn').on('click', function () {
                var data = $(this).data();
                var modal = $('#rejectModal');
                modal.find('.reject-form').attr('action', rejectUrl.replace(':id', data.id));
                modal.find('.reject-reference').text(data.reference);
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
