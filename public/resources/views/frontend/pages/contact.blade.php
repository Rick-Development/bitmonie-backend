@php
    $app_local      = get_default_language_code();
    $default        = App\Constants\LanguageConst::NOT_REMOVABLE;
    $slug           = Illuminate\Support\Str::slug(App\Constants\SiteSectionConst::CONTACT_US_SECTION);
    $contact        = App\Models\Admin\SiteSections::getData($slug)->first();
@endphp
@extends('frontend.layouts.master')

@section('content') 
<section class="contact-section ptb-60">
    <div class="container">
        <div class="contact-tag">
            <h2 class="title"><i class="fas fa-info-circle text--base pb-20"></i> {{ $contact->value->language->$app_local->title ?? $contact->value->language->$default->title ?? '' }}</h2>
        </div>
         <div class="contact-title pb-30">
            <div class="row">
                <div class="col-lg-8">
                   <h3 class="title">{{ $contact->value->language->$app_local->heading ?? $contact->value->language->$default->heading ?? '' }}</h3>
                </div>
            </div>
         </div>
        <div class="row mb-40-none">
            <div class="col-lg-6 mb-40">
                <ul class="contact-item-list">
                    <li class="mb-20">
                        <div class="contact-item-icon">
                            <img src="{{ asset('public/frontend') }}/images/icon/location.gif" alt="icon">
                        </div>
                        <div class="contact-item-content">
                            <h5 class="title">{{ __("Our Location") }}</h5>
                            <span class="sub-title">{{ $contact->value->address ?? '' }}</span>
                        </div>
                    </li>
                    <li class="mb-20">
                        <div class="contact-item-icon">
                            <img src="{{ asset('public/frontend') }}/images/icon/mobile.gif" alt="icon">
                        </div>
                        <div class="contact-item-content">
                            <h5 class="title">{{ __("Call us on") }}: {{ $contact->value->phone ?? '' }}</h5>
                            @foreach ($contact->value->schedules ?? [] as $item)
                                <span class="sub-title">{{ __("Our office hours") }} {{ $item->schedule ?? '' }}</span>
                            @endforeach
                        </div>
                    </li>
                    <li class="mb-20">
                        <div class="contact-item-icon">
                            <img src="{{ asset('public/frontend') }}/images/icon/email.gif" alt="icon">
                        </div>
                        <div class="contact-item-content">
                            <h5 class="title">{{ __("Email us directly") }}</h5>
                            <span class="sub-title">{{ $contact->value->email ?? '' }}</span>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="col-lg-6 mb-40">
                <div class="contact-form-area">
                    <h3 class="title">{{ __("Feel Free To Get In Touch") }} <span class="text--base">{{ __("With Us") }}</span></h3>
                    <form class="contact-form pt-3" action="{{ setRoute('frontend.contact.message.send') }}" method="POST">
                        @csrf
                        <div class="row justify-content-center mb-10-none">
                            <div class="col-xl-6 col-lg-6 col-md-12 form-group">
                                <label>{{ __("Name") }}<span>*</span></label>
                                <input type="text" name="name" class="form--control" placeholder="{{ __("Enter Name") }}...">
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-12 form-group">
                                <label>{{ __("Email") }}<span>*</span></label>
                                <input type="email" name="email" class="form--control" placeholder="{{ __("Enter Email") }}...">
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("Message") }}<span>*</span></label>
                                    <textarea class="form--control" name="message" placeholder="{{ __("Write Here") }}..."></textarea>
                            </div>
                            <div class="col-lg-12 form-group">
                                <button type="submit" class="btn--base mt-10"><span>{{ __("Send Message") }}</span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection