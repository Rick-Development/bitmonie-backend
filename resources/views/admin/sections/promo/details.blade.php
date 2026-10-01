@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title', ['title' => __('Promo Details')])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
            [
                'name' => __('Promos'),
                'url' => setRoute('admin.promo.index'),
            ],
        ],
        'active' => __('Promo Details'),
    ])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">

            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="title">
                        <h4 class="title">{{ __('Promo Details') }}</h4>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ setRoute('admin.promo.edit', $promo->id) }}" class="btn--base">
                            <i class="las la-edit"></i>
                            {{ __('Edit') }}
                        </a>

                        <a href="{{ setRoute('admin.promo.index') }}" class="btn btn--danger">
                            <i class="las la-arrow-left"></i>
                            {{ __('Back') }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">

                {{-- General Information --}}
                <div class="col-xl-6 col-lg-6 mb-20">
                    <div class="table-wrapper">
                        <h5 class="mb-3">{{ __('General Information') }}</h5>

                        <div class="user-list">
                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('Name') }}</span>
                                <strong>{{ $promo->name }}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('Status') }}</span>
                                @if($promo->status === \App\Models\Promo::STATUS_ACTIVE)
                                    <span class="badge badge--success">{{ __('Active') }}</span>
                                @elseif($promo->status === \App\Models\Promo::STATUS_PENDING)
                                    <span class="badge badge--warning">{{ __('Pending') }}</span>
                                @else
                                    <span class="badge badge--danger">{{ __('Inactive') }}</span>
                                @endif
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('Start Date') }}</span>
                                <strong>{{ $promo->start_date?->format('Y-m-d H:i:s') ?? '—' }}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('End Date') }}</span>
                                <strong>{{ $promo->end_date?->format('Y-m-d H:i:s') ?? '—' }}</strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span>{{ __('Expiry Payment') }}</span>
                                <strong>
                                    @if($promo->expiry_payment_percentage !== null)
                                        {{ number_format((float) $promo->expiry_payment_percentage, 2) }}%
                                    @else
                                        —
                                    @endif
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Audit Information --}}
                <div class="col-xl-6 col-lg-6 mb-20">
                    <div class="table-wrapper">
                        <h5 class="mb-3">{{ __('Audit Information') }}</h5>

                        <div class="user-list">
                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('Created By') }}</span>
                                <strong>{{ $promo->creator?->fullname ?? __('N/A') }}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('Updated By') }}</span>
                                <strong>{{ $promo->updater?->fullname ?? __('N/A') }}</strong>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span>{{ __('Created At') }}</span>
                                <strong>{{ $promo->created_at?->format('Y-m-d H:i:s') ?? '—' }}</strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span>{{ __('Updated At') }}</span>
                                <strong>{{ $promo->updated_at?->format('Y-m-d H:i:s') ?? '—' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                <div class="col-xl-12 mb-20">
                    <div class="table-wrapper">
                        <h5 class="mb-3">{{ __('Description') }}</h5>
                        <p>{{ $promo->description ?: __('No description provided.') }}</p>
                    </div>
                </div>

                {{-- Assigned Features --}}
                <div class="col-xl-12">
                    <div class="table-wrapper">
                        <div class="table-header">
                            <div class="title">
                                <h5 class="title">{{ __('Assigned Features') }}</h5>
                            </div>
                        </div>

                        @if($promo->features->count() > 0)
                            <div class="table-responsive">
                                <table class="custom-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('ID') }}</th>
                                            <th>{{ __('Feature') }}</th>
                                            <th>{{ __('Description') }}</th>
                                            <th>{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($promo->features as $feature)
                                            <tr>
                                                <td>{{ $feature->id }}</td>
                                                <td><strong>{{ $feature->name }}</strong></td>
                                                <td>{{ $feature->description ?: '—' }}</td>
                                                <td>
                                                    @if($feature->status === 'active')
                                                        <span class="badge badge--success">{{ __('Active') }}</span>
                                                    @else
                                                        <span class="badge badge--danger">{{ __('Inactive') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            @include('admin.components.alerts.empty', [
                                'title' => __('No features assigned to this promo.'),
                            ])
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection