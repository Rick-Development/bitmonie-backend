@extends('admin.layouts.master')

@section('page-title')
@include('admin.components.page-title', ['title' => __('Promos')])
@endsection

@section('breadcrumb')
@include('admin.components.breadcrumb', [
'breadcrumbs' => [
['name' => __('Dashboard'), 'url' => setRoute('admin.dashboard')],
],
'active' => __('Promos'),
])
@endsection

@section('content') <div class="table-area"> <div class="table-wrapper">

```
        <div class="table-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="title">
                    <h4 class="title">{{ __('Promos') }}</h4>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.promo.create') }}" class="btn--base">
                        <i class="las la-plus"></i>
                        {{ __('Create Promo') }}
                    </a>

                    <a href="{{ route('admin.promo.features') }}" class="btn--base">
                        <i class="las la-list"></i>
                        {{ __('Features') }}
                    </a>

                    <a href="{{ route('admin.promo.statistics') }}" class="btn--base">
                        <i class="las la-chart-bar"></i>
                        {{ __('Statistics') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="table-filter-wrapper mb-20">
            <form action="{{ route('admin.promo.search') }}" method="GET">
                <div class="row align-items-end">

                    <div class="col-xl-4 col-lg-4 col-md-6 mb-10">
                        <label class="form--label">
                            {{ __('Search') }}
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form--control"
                            value="{{ request('search') }}"
                            placeholder="{{ __('Search by promo name or description') }}"
                        >
                    </div>

                    <div class="col-xl-3 col-lg-3 col-md-6 mb-10">
                        <label class="form--label">
                            {{ __('Status') }}
                        </label>

                        <select name="status" class="form--control">
                            <option value="">
                                {{ __('All Statuses') }}
                            </option>

                            <option
                                value="pending"
                                {{ request('status') === 'pending' ? 'selected' : '' }}
                            >
                                {{ __('Pending') }}
                            </option>

                            <option
                                value="active"
                                {{ request('status') === 'active' ? 'selected' : '' }}
                            >
                                {{ __('Active') }}
                            </option>

                            <option
                                value="inactive"
                                {{ request('status') === 'inactive' ? 'selected' : '' }}
                            >
                                {{ __('Inactive') }}
                            </option>
                        </select>
                    </div>

                    <div class="col-xl-3 col-lg-3 col-md-6 mb-10">
                        <label class="form--label">
                            {{ __('Feature') }}
                        </label>

                        <select name="feature" class="form--control">
                            <option value="">
                                {{ __('All Features') }}
                            </option>

                            @foreach($features ?? [] as $feature)
                                <option
                                    value="{{ $feature->id }}"
                                    {{ (string) request('feature') === (string) $feature->id ? 'selected' : '' }}
                                >
                                    {{ $feature->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 col-md-6 mb-10">
                        <button type="submit" class="btn--base w-100">
                            <i class="las la-search"></i>
                            {{ __('Search') }}
                        </button>
                    </div>

                </div>
            </form>
        </div>


        @if($promos->count() > 0)
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Features') }}</th>
                            <th>{{ __('Start Date') }}</th>
                            <th>{{ __('End Date') }}</th>
                            <th>{{ __('Expiry Payment') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($promos as $promo)
                            <tr>
                                <td>
                                    <span class="fw-bold">
                                        {{ $promo->name }}
                                    </span>

                                    @if($promo->description)
                                        <small class="d-block text-muted">
                                            {{ \Illuminate\Support\Str::limit($promo->description, 60) }}
                                        </small>
                                    @endif
                                </td>

                                <td>
                                    @forelse($promo->features as $feature)
                                        <span class="badge badge--info mb-1">
                                            {{ $feature->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted">
                                            {{ __('No features') }}
                                        </span>
                                    @endforelse
                                </td>

                                <td>
                                    {{ $promo->start_date?->format('Y-m-d H:i') }}
                                </td>

                                <td>
                                    {{ $promo->end_date?->format('Y-m-d H:i') }}
                                </td>

                                <td>
                                    @if($promo->expiry_payment_percentage !== null)
                                        {{ number_format((float) $promo->expiry_payment_percentage, 2) }}%
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if($promo->status === \App\Models\Promo::STATUS_ACTIVE)
                                        <span class="badge badge--success">
                                            {{ __('Active') }}
                                        </span>
                                    @elseif($promo->status === \App\Models\Promo::STATUS_PENDING)
                                        <span class="badge badge--warning">
                                            {{ __('Pending') }}
                                        </span>
                                    @else
                                        <span class="badge badge--danger">
                                            {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex gap-2 flex-wrap">

                                        <a
                                            href="{{ route('admin.promo.show', $promo->id) }}"
                                            class="btn btn--base btn--sm"
                                        >
                                            <i class="las la-eye"></i>
                                        </a>

                                        <a
                                            href="{{ route('admin.promo.edit', $promo->id) }}"
                                            class="btn btn--base btn--sm"
                                        >
                                            <i class="las la-edit"></i>
                                        </a>

                                        @if($promo->status === \App\Models\Promo::STATUS_ACTIVE)
                                            <form
                                                action="{{ route('admin.promo.status', $promo->id) }}"
                                                method="POST"
                                            >
                                                @csrf
                                                @method('PATCH')

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="{{ \App\Models\Promo::STATUS_INACTIVE }}"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn--danger btn--sm"
                                                    onclick="return confirm('{{ __('Deactivate this promo?') }}')"
                                                >
                                                    <i class="las la-ban"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <form
                                            action="{{ route('admin.promo.destroy', $promo->id) }}"
                                            method="POST"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="btn btn--danger btn--sm"
                                                onclick="return confirm('{{ __('Delete this promo?') }}')"
                                            >
                                                <i class="las la-trash"></i>
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrapper mt-20">
                {{ $promos->links() }}
            </div>
        @else
            @include('admin.components.alerts.empty', [
                'title' => __('No promos found.')
            ])
        @endif

    </div>
</div>
```

@endsection
