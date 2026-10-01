@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::CHOOSE_US_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<section class="why-choice-us pt-80">
    <div class="container">
       <div class="choice-tag">
           <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $data->value->language->$app_local->heading ?? $data->value->language->$default->heading ?? '' }}</h2>
       </div>
       <div class="choice-title pb-30">
           <div class="row">
               <div class="col-xl-8 col-lg-10">
                   <h3 class="title">{{ $data->value->language->$app_local->sub_heading ?? $data->value->language->$default->sub_heading ?? '' }}</h3>
               </div>
           </div>
       </div>
       <div class="row justify-content-center">
            @foreach ($data->value->items ?? [] as $item)
                <div class="col-xl-4 col-lg-6 col-md-6 pb-20">
                    <div class="choice-item-area">
                        <div class="icon">
                            <i class="{{ $item->icon ?? '' }}"></i>
                        </div>
                        <div class="choice-item-content">
                            <h3 class="title">{{ $item->language->$app_local->title ?? $item->language->$default->title ?? '' }}</h3>
                            <p>{{ $item->language->$app_local->description ?? $item->language->$default->description ?? '' }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
            
       </div>
    </div>
</section>