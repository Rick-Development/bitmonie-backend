@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="row justify-content-center mb-20-none">
    <div class="col-xl-6 col-lg-6 mb-20">
        <div class="custom-card mt-10">
            <div class="kyc-area">
                <div class="card-body">
                    @include('user.components.profile.kyc', compact('user_kyc'))
                 </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')

@endpush
