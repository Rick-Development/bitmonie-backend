@extends('frontend.layouts.master')

@push("css")

@endpush

@section('content')

    @include('frontend.sections.banner')

    @include('frontend.sections.feature')

    @include('frontend.sections.how-its-work')

    @include('frontend.sections.security')

    @include('frontend.sections.overview')

    @include('frontend.sections.choose-us')

    @include('frontend.sections.download-app')

@endsection


@push("script")

@endpush
