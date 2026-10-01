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
    ], 'active' => __("Gift Card Countries")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Gift Card Countries") }}</h5>
                <div class="table-btn-area">
                    <button class="btn btn--primary addBtn"><i class="las la-plus"></i> @lang('Add New')</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>@lang('Name')</th>
                            <th>@lang('Code')</th>
                            <th>@lang('Flag')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($countries as $country)
                            <tr>
                                <td data-label="@lang('Name')">{{ __($country->name) }}</td>
                                <td data-label="@lang('Code')">{{ __($country->code) }}</td>
                                <td data-label="@lang('Flag')">
                                    @if($country->flag_icon)
                                        <div class="user">
                                            <div class="thumb">
                                                <img src="{{ asset($country->flag_icon) }}" alt="flag">
                                            </div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td data-label="@lang('Action')">
                                    <button class="btn btn-sm btn--primary editBtn" 
                                        data-id="{{ $country->id }}" 
                                        data-name="{{ $country->name }}" 
                                        data-code="{{ $country->code }}"
                                        data-flag_icon="{{ $country->flag_icon }}">
                                        <i class="la la-pencil"></i> @lang('Edit')
                                    </button>
                                    
                                    <button class="btn btn-sm btn--danger deleteBtn" 
                                        data-id="{{ $country->id }}">
                                        <i class="la la-trash"></i> @lang('Delete')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage ?? 'No data found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($countries->hasPages())
            <div class="table-footer">
                {{ get_paginate($countries) }}
            </div>
        @endif
    </div>

    {{-- Add Modal --}}
    <div id="addModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Country')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.gift.card.trade.country.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Name')</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Code')</label>
                            <input type="text" name="code" class="form-control" maxlength="3" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Flag Icon URL') <small>(Optional)</small></label>
                            <input type="text" name="flag_icon" class="form-control" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>@lang('Or Upload Flag')</label>
                            <input type="file" name="flag" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Edit Country')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Name')</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Code')</label>
                            <input type="text" name="code" class="form-control" maxlength="3" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Flag Icon URL') <small>(Optional)</small></label>
                            <input type="text" name="flag_icon" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>@lang('Or Upload Flag')</label>
                            <input type="file" name="flag" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary w-100">@lang('Update')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div id="deleteModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Confirmation')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body">
                        <p>@lang('Are you sure to delete this country?')</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--danger">@lang('Delete')</button>
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
            $('.addBtn').on('click', function () {
                var modal = $('#addModal');
                modal.modal('show');
            });

            $('.editBtn').on('click', function () {
                var modal = $('#editModal');
                var data = $(this).data();
                var action = "{{ route('admin.gift.card.trade.country.update', ':id') }}";
                action = action.replace(':id', data.id);
                modal.find('form').attr('action', action);
                modal.find('input[name=name]').val(data.name);
                modal.find('input[name=code]').val(data.code);
                modal.find('input[name=flag_icon]').val(data.flag_icon);
                modal.modal('show');
            });

            $('.deleteBtn').on('click', function () {
                var modal = $('#deleteModal');
                var data = $(this).data();
                var action = "{{ route('admin.gift.card.trade.country.delete', ':id') }}";
                action = action.replace(':id', data.id);
                modal.find('form').attr('action', action);
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
