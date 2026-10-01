@php
    $app_local       = get_default_language_code();
    $default         = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug            = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::OVERVIEW_SECTION);
    $overview        = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Overview Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="overview-section pt-80">
    <div class="container">
        <div class="overview-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $overview->value->language->$app_local->heading ?? $overview->value->language->$default->heading ?? '' }}</h2>
        </div>
        <div class="overview-title pb-30">
            <div class="row">
                <div class="col-xl-8 col-lg-10">
                    <h3 class="title">{{ $overview->value->language->$app_local->sub_heading ?? $overview->value->language->$default->sub_heading ?? '' }}</h3>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="overview-area">
                    <div class="overview-img">
                        <img src="{{ get_image($overview->value->image ?? '' , 'site-section') }}" alt="img">
                    </div>
                    <div class="statistics-area">
                        @foreach ($overview->value->items ?? [] as $item)
                            <div class="overview-details-area">
                                <div class="counter">
                                    <div class="odo-area d-flex">
                                        <h5 class="odo-title odometer" data-odometer-final="{{ formatNumber($item->counter_value ?? '') }}">{{ formatNumber($item->counter_value ?? '') }}</h5>
                                        <h5 class="title">{{ formatNotation($item->counter_value ?? '') }}</h5>
                                    </div>
                                </div>
                                <div class="overview-details">
                                    <h4 class="title">{{ $item->language->$app_local->title ?? $item->language->$default->title ?? '' }}</h4>
                                    <p>{{ $item->language->$app_local->description ?? $item->language->$default->description ?? '' }}</p>
                                </div>
                            </div>
                        @endforeach
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Overview Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->