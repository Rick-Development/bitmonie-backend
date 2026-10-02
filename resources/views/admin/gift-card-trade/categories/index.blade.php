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
    ], 'active' => __("Gift Card Categories")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Gift Card Categories") }}</h5>
                <div class="table-btn-area">
                    <button class="btn btn--primary addBtn"><i class="las la-plus"></i> @lang('Add New')</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>@lang('Name')</th>
                            <th>@lang('Icon')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td data-label="@lang('Name')">{{ __($category->name) }}</td>
                                <td data-label="@lang('Icon')">
                                    <ul class="user-list">
                                        <li><img src="{{ asset($category->icon) }}" alt="icon"></li>
                                    </ul>
                                </td>
                                <td data-label="@lang('Status')">
                                    <span class="{{ $category->status == 1 ? 'badge badge--success' : 'badge badge--danger' }}">{{ $category->status == 1 ? __('Active') : __('Inactive') }}</span>
                                </td>
                                <td data-label="@lang('Action')">
                                    <button class="btn btn-sm btn--primary editBtn" 
                                        data-id="{{ $category->id }}" 
                                        data-name="{{ $category->name }}" 
                                        data-icon="{{ $category->icon }}"
                                        data-description="{{ $category->description }}">
                                        <i class="la la-pencil"></i> @lang('Edit')
                                    </button>
                                    
                                    <button class="btn btn-sm btn--{{ $category->status == 1 ? 'danger' : 'success' }} statusBtn" 
                                        data-id="{{ $category->id }}" 
                                        data-status="{{ $category->status }}">
                                        @if($category->status == 1)
                                            <i class="la la-eye-slash"></i> @lang('Disable')
                                        @else
                                            <i class="la la-eye"></i> @lang('Enable')
                                        @endif
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
        @if ($categories->hasPages())
            <div class="table-footer">
                {{ get_paginate($categories) }}
            </div>
        @endif
    </div>

    {{-- Add Modal --}}
    <div id="addModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Category')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.gift.card.trade.category.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Name')</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Icon URL') <small>(Optional)</small></label>
                            <input type="text" name="icon" class="form-control" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>@lang('Or Upload Image')</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
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
                    <h5 class="modal-title">@lang('Edit Category')</h5>
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
                            <label>@lang('Icon URL') <small>(Optional)</small></label>
                            <input type="text" name="icon" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>@lang('Or Upload Image')</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary w-100">@lang('Update')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Status Modal --}}
    <div id="statusModal" class="modal fade" tabindex="-1" role="dialog">
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
                    @method('PUT')
                    <div class="modal-body">
                        <p>@lang('Are you sure to change status?')</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--primary">@lang('Confirm')</button>
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
                var action = "{{ route('admin.gift.card.trade.category.update', ':id') }}";
                action = action.replace(':id', data.id);
                modal.find('form').attr('action', action);
                modal.find('input[name=name]').val(data.name);
                modal.find('input[name=icon]').val(data.icon);
                modal.modal('show');
            });

            $('.statusBtn').on('click', function () {
                var modal = $('#statusModal');
                var data = $(this).data();
                var action = "{{ route('admin.gift.card.trade.category.status', ':id') }}";
                action = action.replace(':id', data.id);
                modal.find('form').attr('action', action);
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush
