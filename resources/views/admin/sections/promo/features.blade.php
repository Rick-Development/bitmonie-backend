@extends('admin.layouts.master')

@section('page-title')
    @include('admin.components.page-title', ['title' => __('Promo Features')])
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
                'url' => route('admin.promo.index'),
            ],
        ],
        'active' => __('Features'),
    ])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">

            {{-- ============================================================
                 TABLE HEADER
                 ============================================================ --}}
            <div class="table-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <div class="title">
                        <h4 class="title">
                            {{ __('Promo Features') }}
                        </h4>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">

                        <a
                            href="{{ route('admin.promo.index') }}"
                            class="btn--base"
                        >
                            <i class="las la-arrow-left"></i>
                            {{ __('Back to Promos') }}
                        </a>

                    </div>

                </div>
            </div>


            {{-- ============================================================
                 FEATURES TABLE
                 ============================================================ --}}
            @if($features->count() > 0)

                <div class="table-responsive">
                    <table class="custom-table">

                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Feature') }}</th>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($features as $feature)

                                <tr>

                                    {{-- ID --}}
                                    <td>
                                        {{ $feature->id }}
                                    </td>


                                    {{-- NAME --}}
                                    <td>
                                        <span class="fw-bold">
                                            {{ $feature->name }}
                                        </span>
                                    </td>


                                    {{-- CODE --}}
                                    <td>
                                        @if(!empty($feature->code))
                                            <span class="badge badge--info">
                                                {{ $feature->code }}
                                            </span>
                                        @else
                                            <span class="text-muted">
                                                —
                                            </span>
                                        @endif
                                    </td>


                                    {{-- DESCRIPTION --}}
                                    <td>
                                        @if(!empty($feature->description))
                                            {{ \Illuminate\Support\Str::limit($feature->description, 80) }}
                                        @else
                                            <span class="text-muted">
                                                {{ __('No description') }}
                                            </span>
                                        @endif
                                    </td>


                                    {{-- STATUS --}}
                                    <td>
                                        @if(
                                            isset($feature->is_active)
                                            && $feature->is_active
                                        )
                                            <span class="badge badge--success">
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="badge badge--danger">
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>
                </div>

            @else

                @include('admin.components.alerts.empty', [
                    'title' => __('No promo features found.')
                ])

            @endif

        </div>
    </div>
@endsection