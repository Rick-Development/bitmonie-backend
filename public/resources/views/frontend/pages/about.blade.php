@extends('frontend.layouts.master')
@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::ABOUT_US_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp

@section('content')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    About Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->

<section class="about-section pt-60">
    <div class="container">
        <div class="row mb-30-none">
            <div class="col-xl-6 col-lg-12 mb-30">
                <div class="about-img">
                    <img src="{{ get_image($data->value->image ?? '' ,'site-section') }}" alt="img">
                </div>
            </div>
            <div class="col-xl-6 col-lg-12 mb-30">
               <div class="about-area">
                    <div class="about-section-tag">
                        <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $data->value->language->$app_local->title ?? $data->value->language->$default->title ?? '' }}</h2>
                    </div>
                    <div class="about-section-title pb-20">
                        <div class="row">
                            <div class="col-lg-12">
                                <h3 class="title">{{ $data->value->language->$app_local->heading ?? $data->value->language->$default->heading ?? '' }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="about-details">
                        <p>{{ $data->value->language->$app_local->sub_heading ?? $data->value->language->$default->sub_heading ?? '' }}</p>
                    </div>
               </div>
            </div>
        </div>
    </div>
</section>

@include('frontend.sections.faq')

@include('frontend.sections.testimonial')

@endsection