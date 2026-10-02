@extends('admin.layouts.master')

@section('page-title')
    {{ __($page_title) }}
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Management")])
@endsection

@section('content')
<div class="mb-4">
    <form action="{{ setRoute('admin.gift.card.sync.metadata') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn--base"><i class="las la-sync"></i> {{ __("Sync From Reloadly") }}</button>
    </form>
</div>

<div class="row mb-none-30">
    <div class="col-xl-6 col-lg-6 mb-30">
        <div class="table-area">
            <div class="table-wrapper">
                <div class="table-header">
                    <h5 class="title">{{ __("Categories") }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>{{ __("Name") }}</th>
                                <th>{{ __("Status") }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $cat)
                                <tr>
                                    <td>{{ $cat->name }}</td>
                                    <td>
                                        <span class="badge {{ $cat->status ? 'badge--success' : 'badge--danger' }}">
                                            {{ $cat->status ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn--base toggleBtn" data-type="category" data-id="{{ $cat->id }}">
                                            <i class="las la-exchange-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6 mb-30">
        <div class="table-area">
            <div class="table-wrapper">
                <div class="table-header">
                    <h5 class="title">{{ __("Countries") }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>{{ __("Name") }}</th>
                                <th>{{ __("Currency") }}</th>
                                <th>{{ __("Status") }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($countries as $country)
                                <tr>
                                    <td><img src="{{ $country->flag_url }}" width="20"> {{ $country->name }}</td>
                                    <td>{{ $country->currency_code }}</td>
                                    <td>
                                        <span class="badge {{ $country->status ? 'badge--success' : 'badge--danger' }}">
                                            {{ $country->status ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn--base toggleBtn" data-type="country" data-id="{{ $country->id }}">
                                            <i class="las la-exchange-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    $('.toggleBtn').on('click', function() {
        var type = $(this).data('type');
        var id = $(this).data('id');
        var url = "{{ setRoute('admin.gift.card.status.toggle') }}";

        $.post(url, {
            _token: "{{ csrf_token() }}",
            type: type,
            id: id
        }, function(res) {
            location.reload();
        });
    });
</script>
@endpush
