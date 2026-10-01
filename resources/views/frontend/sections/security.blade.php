@php
    $app_local      = get_default_language_code();
    $default        = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug           = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::SECURITY_SECTION);
    $security       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<section class="security-system pt-80">
    <div class="container">
        <div class="security-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $security->value->language->$app_local->heading ?? $security->value->language->$default->heading ?? '' }}</h2>
        </div>
         <div class="security-title pb-30">
            <div class="row">
                <div class="col-xl-8 col-lg-10">
                    <h3 class="title">{{ $security->value->language->$app_local->sub_heading ?? $security->value->language->$default->sub_heading ?? '' }}</h3>
                </div>
            </div>
         </div>
        <div class="row justify-content-center">
            @foreach ($security->value->items ?? [] as $item)
            <div class="col-xl-4 col-lg-6 col-md-6 pb-20">
                <div class="security-item">
                    <span class="icon"><i class="{{ $item->icon ?? '' }}"></i></span>
                    <div class="security-content">
                        <h4 class="title">{{ $item->language->$app_local->title ?? $item->language->$default->title ?? '' }}</h4>
                        <p>{{ $item->language->$app_local->description ?? $item->language->$default->description ?? '' }}</p>
                    </div>
                </div>
            </div>
            @endforeach
            
        </div>
    </div>
</section>
