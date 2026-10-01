@extends('frontend.layouts.master')
@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::ANNOUNCEMENT_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp

@section('content')
<section class="blog-section ptb-60">
    <div class="container">
        <div class="blog-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base pb-20"></i> {{ $data->value->language->$app_local->heading ?? $data->value->language->$default->heading ?? '' }}</h2>
        </div>
        <div class="blog-title pb-30">
            <div class="row">
                <div class="col-lg-8">
                    <h3 class="title">{{ $data->value->language->$app_local->sub_heading ?? $data->value->language->$default->sub_heading ?? '' }}</h3>
                </div>
            </div>
        </div>
        <div class="row justify-content-center mb-30-none">
            @foreach ($journals ?? [] as $item)
            <div class="col-xl-4 col-lg-6 col-md-6 mb-30">
                <div class="blog-item">
                    <div class="blog-thum">
                        <img src="{{ get_image($item->data->image ?? '','site-section') }}" alt="img" >
                    </div>
                    <div class="blog-content">
                        <a href="{{ setRoute('frontend.journal.details',$item->slug) }}">
                            <h3 class="title">{{ Str::words($item->data->language->$app_local->title ?? $item->data->language->$default->title,"5","...") }}</h3>
                        </a>
                        <p>{!! Str::words($item->data->language->$app_local->description ?? $item->data->language->$default->description,"15","...") !!}</p>
                    </div>
                    <div class="blog-footer">
                        <div class="blog-btn">
                            <a href="{{ setRoute('frontend.journal.details',$item->slug) }}" class="btn--base">{{ __("Read More") }} <i class="las la-chevron-right"></i></a>
                        </div>
                        <div class="blog-date">
                            <span class="text--base">{{ \Carbon\Carbon::parse($item->created_at)->format('F j, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endsection