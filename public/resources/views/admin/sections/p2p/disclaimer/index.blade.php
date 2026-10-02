@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("P2P Disclaimers")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __($page_title) }}</h5>
                <div class="table-btn-area">
                    <a href="#create-modal" class="btn btn--base modal-btn"><i class="las la-plus me-1"></i> {{__("Add New")}}</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{__('Key')}}</th>
                            <th>{{__('Title')}}</th>
                            <th>{{__('Type')}}</th>
                            <th>{{__('Required')}}</th>
                            <th>{{__('Active')}}</th>
                            <th>{{__('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($disclaimers as $item)
                            <tr>
                                <td>{{ $item->key }}</td>
                                <td>{{ $item->title }}</td>
                                <td><span class="badge badge--info">{{ $item->type }}</span></td>
                                <td>{{ $item->requires_acceptance ? 'Yes' : 'No' }}</td>
                                <td>
                                    <span class="badge {{ $item->is_active ? 'badge--success' : 'badge--danger' }}">{{ $item->is_active ? 'Yes' : 'No' }}</span>
                                </td>
                                <td>
                                    <a href="#edit-modal-{{ $item->id }}" class="btn btn--base modal-btn"><i class="las la-pen"></i></a>
                                    <form action="{{ setRoute('admin.p2p.disclaimers.destroy', $item->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger" onclick="return confirm('Delete?')"><i class="las la-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            @include('admin.components.alerts.empty',['colspan' => 6])
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="create-modal" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Add Disclaimer") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="card-body" action="{{ setRoute('admin.p2p.disclaimers.store') }}" method="POST">
                    @csrf
                    <div class="row mb-10-none">
                        <div class="col-xl-6 col-lg-6 form-group">
                            <label>{{ __("Key") }}*</label>
                            <input type="text" name="key" class="form-control" placeholder="Unique Key" required>
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            <label>{{ __("Title") }}*</label>
                            <input type="text" name="title" class="form-control" placeholder="Title" required>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            <label>{{ __("Content") }}*</label>
                            <textarea name="content" class="form-control summernote" required rows="4"></textarea>
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            <label>{{ __("Type") }}</label>
                            <select name="type" class="form-control">
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-xl-3 col-lg-3 form-group">
                            <label>{{ __("Requires Acceptance") }}</label>
                            <input type="checkbox" name="requires_acceptance" value="1">
                        </div>
                        <div class="col-xl-3 col-lg-3 form-group">
                            <label>{{ __("Active") }}</label>
                            <input type="checkbox" name="is_active" value="1" checked>
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            <button type="submit" class="btn btn--base w-100">{{ __("Add") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($disclaimers as $item)
        <div id="edit-modal-{{ $item->id }}" class="mfp-hide large">
            <div class="modal-data">
                <div class="modal-header px-0">
                    <h5 class="modal-title">{{ __("Edit Disclaimer") }}</h5>
                </div>
                <div class="modal-form-data">
                    <form class="card-body" action="{{ setRoute('admin.p2p.disclaimers.update', $item->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row mb-10-none">
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Key") }}*</label>
                                <input type="text" name="key" class="form-control" value="{{ $item->key }}" required>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Title") }}*</label>
                                <input type="text" name="title" class="form-control" value="{{ $item->title }}" required>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("Content") }}*</label>
                                <textarea name="content" class="form-control summernote" required rows="4">{{ $item->content }}</textarea>
                            </div>
                            <div class="col-xl-6 col-lg-6 form-group">
                                <label>{{ __("Type") }}</label>
                                <select name="type" class="form-control">
                                    <option value="info" {{ $item->type == 'info' ? 'selected' : '' }}>Info</option>
                                    <option value="warning" {{ $item->type == 'warning' ? 'selected' : '' }}>Warning</option>
                                    <option value="critical" {{ $item->type == 'critical' ? 'selected' : '' }}>Critical</option>
                                </select>
                            </div>
                            <div class="col-xl-3 col-lg-3 form-group">
                                <label>{{ __("Requires Acceptance") }}</label>
                                <input type="checkbox" name="requires_acceptance" value="1" {{ $item->requires_acceptance ? 'checked' : '' }}>
                            </div>
                            <div class="col-xl-3 col-lg-3 form-group">
                                <label>{{ __("Active") }}</label>
                                <input type="checkbox" name="is_active" value="1" {{ $item->is_active ? 'checked' : '' }}>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <button type="submit" class="btn btn--base w-100">{{ __("Update") }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('css')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .note-editor.note-airframe, .note-editor.note-frame {
            border: 1px solid #e5e5e5;
            border-radius: 5px;
        }
    </style>
@endpush

@push('script')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 200,
                dialogsInBody: true
            });
        });
    </script>
@endpush
