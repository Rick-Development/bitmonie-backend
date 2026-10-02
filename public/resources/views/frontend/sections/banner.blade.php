@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::BANNER_SECTION);
    $banner     = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Banner Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="banner-section banner-overlay  bg_img" data-background="{{ get_image($banner->value->image ?? '' ,'site-section') }}">
    <div class="container">
        <div class="row">
            <div class="col-xxl-6 col-lx-6 col-lg-8">
                <div class="banner-content">
                    @php
                        $headingParts = explode('|', $banner->value->language->$app_local->heading ?? $banner->value->language->$default->heading ?? '');
                    @endphp
                    <h1 class="title">
                        {{ isset($headingParts[0]) ? trim($headingParts[0]) : '' }} 
                        @if(isset($headingParts[1]))
                            <span class="text--base">{{ trim($headingParts[1]) }}</span>
                        @endif
                        {{ isset($headingParts[2]) ? trim($headingParts[2]) : '' }}
                        @if(isset($headingParts[3]))
                            <span class="text--base">{{ trim($headingParts[3]) }}</span>
                        @endif
                    </h1>
                    <p>{{ $banner->value->language->$app_local->sub_heading ??  $banner->value->language->$default->sub_heading ?? '' }}</p>
                    <div class="banner-btn">
                        <a href="{{ setRoute('user.login') }}" class="btn--base">{{ $banner->value->language->$app_local->button_name ??  $banner->value->language->$default->button_name ?? '' }} <i class="las la-chevron-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Banner Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->