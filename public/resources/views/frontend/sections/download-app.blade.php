@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::DOWNLOAD_APP_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    App Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<div class="app-section ptb-80">
    <div class="container">
        <div class="app-section-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ @$data->value->language->$app_local->title ?? @$data->value->language->$default->title ?? '' }} </h2>
        </div>
        <div class="app-section-title pb-30">
            <div class="row">
                <div class="col-lg-8">
                   <h3 class="title">{{ @$data->value->language->$app_local->heading ?? @$data->value->language->$default->heading ?? '' }}</h3>
                </div>
            </div>
        </div>
        <div class="row mb-20-none">
           <div class="col-lg-6 mb-20">
              <div class="app-area-content">
                  <p>{{ @$data->value->language->$app_local->sub_heading ?? @$data->value->language->$default->sub_heading ?? '' }}</p>
              </div>
             <div class="row mb-30-none">
                <div class="col-xl-12 col-lg-8 mb-30">
                    <div class="app-btn-wrapper">
                        @foreach ($data->value->items ?? [] as $item)
                        <a href="#0" class="app-btn">
                            <div class="app-icon">
                                <img src="{{ get_image($item->icon_image ?? '', 'site-section') }}" alt="icon">
                            </div>
                            <div class="content">
                                <span>{{ @$item->language->$app_local->item_title ?? @$item->language->$default->item_title ?? '' }}</span>
                                <h5 class="title">{{ @$item->language->$app_local->item_header ?? @$item->language->$default->item_header ?? '' }}</h5>
                            </div>
                            <div class="icon">
                                <img src="{{ get_image($item->image, 'site-section') }}" alt="element">
                            </div>
                            <div class="app-qr">
                                <img src="{{ get_image($item->image, 'site-section') }}" alt="element">
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
             </div>
          </div>
          <div class="col-lg-6 mb-20">
            <div class="app-img">
               <img src="{{ get_image($data->value->image ?? '' ,'site-section') }}" alt="img">
            </div>
        </div>
       </div>
    </div>
</div>