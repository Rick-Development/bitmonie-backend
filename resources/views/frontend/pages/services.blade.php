@extends('frontend.layouts.master')
@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::SERVICES_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp

@section('content')
<section class="service-section ptb-60">
    <div class="container">
        <div class="service-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $data->value->language->$app_local->heading ?? $data->value->language->$default->heading ?? '' }}</h2>
        </div>
        <div class="service-title pb-30">
            <div class="row">
                <div class="col-lg-8">
                    <h3 class="title">{{ $data->value->language->$app_local->sub_heading ?? $data->value->language->$default->sub_heading ?? '' }}</h3>
                </div>
            </div>
        </div>
        <div class="service-area">
            <div class="row justify-content-center mb-20-none">
                @foreach ($data->value->items ?? [] as $item)
                <div class="col-lg-4 col-md-6 mb-20">
                    <div class="service-item">
                        <div class="icon">
                            <i class="{{ $item->icon ?? '' }}"></i>
                        </div>
                        <div class="service-details">
                            <h3 class="title">{{ $item->language->$app_local->title ?? $item->language->$default->title ?? '' }}</h3>
                            <p>{{ $item->language->$app_local->description ?? $item->language->$default->description ?? '' }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endsection