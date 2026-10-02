@php
    $app_local      = get_default_language_code();
    $default        = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug           = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::FOOTER_SECTION);
    $footer         = App\Models\Admin\SiteSections::getData($slug)->first();
    $subcribe_slug  = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::SUBSCRIBE_SECTION);
    $subscribe      = App\Models\Admin\SiteSections::getData($subcribe_slug)->first();
    $useful_links   = App\Models\Admin\UsefulLink::where('status',true)->get()
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        Start Footer
 ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
 <footer class="footer-section bg-overlay bg_img" data-background="{{ asset('frontend/') }}/images/element/footer-shape.jpg">
    <div class="container mx-auto">
       <div class="footer-content pt-60">
               <div class="row">
                   <div class="col-xl-4 col-lg-4 mb-50">
                       <div class="footer-widget">
                           <div class="footer-text">
                            <img src="{{ $footer->value->footer->image ? get_image($footer->value->footer->image,'site-section') : get_logo($basic_settings) }}" alt="image">
                               <p>{{ $footer->value->footer->language->$app_local->description ?? $footer->value->footer->language->$default->description ?? '' }}</p>
                           </div>
                           <div class="footer-social-icon">
                                @php
                                    $items = $footer->value->social_links ?? [];
                                @endphp
                               <span>{{ __("Follow us") }} :</span>
                               @foreach ($items as $item)
                                    <a href="{{ $item->link ?? "" }}" target="_blank"><i class="{{ $item->icon ?? ""}}"></i></a>
                                @endforeach
                           </div>
                       </div>
                   </div>
                   <div class="col-xl-4 col-lg-4 col-md-6 mb-30">
                       <div class="footer-widget">
                            <div class="footer-widget-heading">
                                <h3>{{ __("Useful Links") }}</h3>
                            </div>
                            <ul>
                                @foreach ($useful_links ?? [] as $item)
                                    <li><a href="{{ setRoute('frontend.useful.links',$item->slug) }} ">{{ $item->title->language->$app_local->title ?? $item->title->language->$default->title ?? '' }}</a></li>
                                @endforeach
                            </ul>
                       </div>
                   </div>
                   <div class="col-xl-4 col-lg-4 col-md-6 mb-50">
                       <div class="footer-widget">
                           <div class="footer-widget-heading">
                               <h3>{{ $subscribe->value->language->$app_local->title ??  $subscribe->value->language->$default->title ?? '' }}</h3>
                           </div>
                           <div class="footer-text mb-25">
                               <p>{{ $subscribe->value->language->$app_local->description ??  $subscribe->value->language->$default->description ?? '' }}</p>
                           </div>
                           <div class="subscribe-form">
                               <form id="subscribe-form" action="{{ setRoute('frontend.subscribe') }} " method="POST">
                                @csrf
                                   <input type="email" class="form--control" name="email" placeholder="{{ __("Email Address") }}">
                                   <button><i class="fab fa-telegram-plane"></i></button>
                               </form>
                           </div>
                       </div>
                   </div>
               </div>
       </div>
   </div>
   <div class="copyright-area">
       <div class="container">
           <div class="row">
               <div class="col-12 text-center text-lg-left">
                   <div class="copyright-text">

                        <p>{{ __("Copyright") }} &copy; {{ date('Y') }}, {{ __("All Rights Reserved By") }} <a href="{{ setRoute('frontend.index') }}">{{ $basic_settings->site_name ?? '' }}</a></p>
                    </div>
               </div>
            </div>
       </div>
   </div>
</footer>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
       End Footer
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
