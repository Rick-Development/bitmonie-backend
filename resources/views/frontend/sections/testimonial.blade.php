@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::CLIENT_FEEDBACK_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Testimonial Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="testimonial-section ptb-80">
    <div class="container">
        <div class="testimonial-title pb-20">
            <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $data->value->language->$app_local->title ?? $data->value->language->$default->title ?? '' }}</h2>
        </div>
        <div class="row mb-40-none">
            <div class="col-lg-6 col-md-12 mb-40">
                <div class="customer-says">
                    <h3 class="title">{{ $data->value->language->$app_local->heading ?? $data->value->language->$default->heading ?? '' }}</h3>
                    <p>{{ $data->value->language->$app_local->sub_heading ?? $data->value->language->$default->sub_heading ?? '' }}</p>

                    @php
                        $reviews        = $data->value->items ?? [];
                        $reviews_data   = (array) $reviews;
                        $total_reviews  = count($reviews_data) ?? 0;
                        $latest_reviews = array_slice($reviews_data, -2) ?? [];
                    @endphp
                    

                    <div class="client-img d-flex">
                        @foreach ($latest_reviews ?? [] as $item)
                            <div class="img-1">
                                <img src="{{ get_image($item->image ?? '' ,'site-section') }}" alt="client">
                            </div>
                        @endforeach 
                    </div>
                    <div class="comment pt-2">
                        <P><span class="text--base">{{ $total_reviews ?? 0 }}+</span>{{ __("Customer Reviews") }}</P>
                    </div>
                </div>
             </div>
            <div class="col-lg-6 col-md-12 mb-40">
                 <div class="testimonial-section">
                    <div class="testimonial-slider">
                        <div class="swiper-wrapper">
                            @foreach ($data->value->items ?? [] as $item)
                                <div class="swiper-slide">
                                    <div class="testimonial-wrapper">
                                        <div class="testimonial-ratings">
                                            @for ($i = 1; $i <= $item->star; $i++)
                                                <i class="fas fa-star"></i>
                                            @endfor
                                        </div>
                                        <p>"{{ $item->language->$app_local->comment ?? $item->language->$app_local->comment ?? '' }}"</p>
                                        <div class="client-details d-flex justify-content-between">
                                            <div class="client-img">
                                                <img src="{{ get_image($item->image ?? '' ,'site-section') }}" alt="client">
                                            </div>
                                            <div class="client-title text--base">
                                                <h4 class="title text--base">{{ $item->name ?? '' }}</h4>
                                                <P>{{ $item->designation ?? '' }}</P>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </div>
</section>