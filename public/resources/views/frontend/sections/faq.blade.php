@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug       = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::FAQ_SECTION);
    $data       = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    FAQ Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="faq-section pt-80">
    <div class="container">
        <div class="faq-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $data->value->language->$app_local->title ?? $data->value->language->$default->title ?? '' }}</h2>
        </div>
        <div class="faq-title pb-30">
            <div class="row">
                <div class="col-lg-8">
                    <h3 class="title">{{ $data->value->language->$app_local->heading ?? $data->value->language->$default->heading ?? '' }}</h3>
                </div>
            </div>
        </div>
        <div class="row justify-content-center mb-20-none">
            @php
                $items      = $data->value->items ?? [];
                $item_data   = (array) $items;
                
                $converted_data = count($item_data) > 0 ? array_chunk($item_data, ceil(count($item_data) / 2)) : [[], []];

                $part1 = $converted_data[0];
                $part2 = $converted_data[1];

            @endphp 
            <div class="col-xl-6 col-lg-6 mb-20">
                <div class="faq-wrapper">
                    @foreach ($part1 ?? [] as $item)
                        @if ($item->status == 1)
                            <div class="faq-item">
                                <h3 class="faq-title"><span class="title">{{ $item->language->$app_local->question ?? $item->language->$default->question ?? '' }}</span><span class="right-icon"></span></h3>
                                <div class="faq-content">
                                    <p>{{ $item->language->$app_local->answer ?? $item->language->$default->answer ?? '' }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="col-xl-6 col-lg-6 mb-20">
                <div class="faq-wrapper pb-30">
                    @foreach ($part2 ?? [] as $item)
                        @if ($item->status == 1)
                            <div class="faq-item">
                                <h3 class="faq-title"><span class="title"> {{ $item->language->$app_local->question ?? $item->language->$default->question ?? '' }}</span><span class="right-icon"></span></h3>
                                <div class="faq-content">
                                    <p>{{ $item->language->$app_local->answer ?? $item->language->$default->answer ?? '' }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
           </div>
      </div>
    </div>
</section>