@extends('frontend.layouts.master')
@php
    $app_local  = get_default_language_code();
    $default    = App\Constants\LanguageConst::NOT_REMOVABLE;
@endphp

@section('content')
<div class="privacy-section section--bg ptb-120">
    <div class="container">
        <p>{!! $useful_link->content->language->$app_local->content ?? $useful_link->content->language->$default->content ?? '' !!}</p>
    </div>
</div>

@endsection