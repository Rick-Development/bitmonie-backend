@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::FEATURE_SECTION);
    $feature    = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Key Features Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="key-features pt-80">
    <div class="container">
        <div class="features-area">
            <div class="feature-tag">
                <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $feature->value->language->$app_local->first_heading ?? $feature->value->language->$default->first_heading ?? '' }}</h2>
            </div>
            <div class="feature-title pb-30">
               <div class="row">
                  <div class="col-xl-8 col-lg-10">
                     <h3 class="title">{{ $feature->value->language->$app_local->first_sub_heading ?? $feature->value->language->$default->first_sub_heading ?? '' }}</h3>
                  </div>
               </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="featuare-content-area">
                        <ul class="feature-list">
                            @foreach ($feature->value->items ?? [] as $item)
                                @if ($item->status == true)
                                    <li>
                                        <i class="las la-arrow-right"></i> {{ $item->language->$app_local->item_title ?? $item->language->$default->item_title ?? '' }}
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                        <div class="key-deatils">
                            <h3 class="title">{{ $feature->value->language->$app_local->second_heading ?? $feature->value->language->$default->second_heading ?? '' }}</h3>
                            <p>{{ $feature->value->language->$app_local->second_sub_heading ?? $feature->value->language->$default->second_sub_heading ?? '' }}</p>
                            <div class="contact-btn">
                                <a href="{{ setRoute('frontend.contact') }}" class="btn--base">{{ $feature->value->language->$app_local->button_name ?? $feature->value->language->$default->button_name ?? '' }}<i class="las la-chevron-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
   End Key Features Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->