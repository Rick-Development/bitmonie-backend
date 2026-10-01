@php
    $app_local       = get_default_language_code();
    $default         = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug            = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::HOW_IT_WORK_SECTION);
    $how_its_work    = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    how it work Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="how-it-work pt-80">
    <div class="container">
       <div class="row align-items-center mb-30-none">
          <div class="col-xl-6 col-lg-12 mb-30">
             <div class="how-it-work-img">
                 <img src="{{ get_image($how_its_work->value->image ?? '', 'site-section') }}" alt="img">
             </div>
          </div>
          <div class="col-xl-6 col-lg-12 mb-30">
             <div class="how-it-work-content">
                <div class="how-it-work-tag">
                    <h2 class="title"><i class="fas fa-info-circle text--base mb-20"></i> {{ $how_its_work->value->language->$app_local->heading ?? $how_its_work->value->language->$default->heading ?? '' }}</h2>
                </div>
                 <div class="how-it-work-title pb-30">
                    <div class="row">
                        <div class="col-lg-12">
                            <h3 class="title">{{ $how_its_work->value->language->$app_local->sub_heading ?? $how_its_work->value->language->$default->sub_heading ?? '' }}</h3>
                        </div>
                    </div>
                 </div>
                <div class="row align-items-center">
                    @php
                        $key = 1;
                    @endphp
                    @foreach ($how_its_work->value->items ?? [] as $item)
                        @if ($item->status == true)
                            <div class="col-12 mb-20">
                                <div class="working-list" data-aos="fade-left" data-aos-duration="1200">
                                    <div class="number">
                                        <h3 class="title">{{ $key++ }}</h3>
                                    </div>
                                    <div class="work-content tri-right left-top">
                                        <p>{{ $item->language->$app_local->item_title ?? $item->language->$default->item_title ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
             </div>
          </div>
       </div>
    </div>
</section>

<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    how it work Section
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
