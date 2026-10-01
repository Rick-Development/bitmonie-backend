@extends('user.layouts.master')

@push('css')

@endpush

@section('content')
<div class="row justify-content-center mt-30">
    <div class="col-lg-8 col-md-10">
        <div class="transfer-preview">
            <div class="transfer-preview-area">
                <div class="preview-area-title pb-10">
                    <h3 class="title">{{ __($page_title) }}</h3>
                </div>
                @php
                    $data = $temp_data->data;
                @endphp
               <div class="preview-list-wrapper">
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-receipt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Money Out Amount') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--success withdraw-amount">{{ get_amount($data->charges->request_amount, $data->charges->sender_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-battery-half"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Total Fees & Charges') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="total-charges">{{ get_amount($data->charges->total_charge, $data->charges->sender_currency) }}</span>
                        </div>
                    </div>
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="lab la-get-pocket"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span>{{ __('Will Get') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="will-get">{{ get_amount($data->charges->will_get, $data->charges->receive_currency) }}</span>
                        </div>
                    </div>
                    @foreach ($data->get_values as $item)
                        @if ($item->type == "file")
                            @php
                                $file_link = get_file_link("kyc-files",$item->value);
                            @endphp
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __($item->label) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--info last payable">
                                        @if (its_image($item->value))
                                            <span class="product-sales-thumb">
                                                <img id="myImg" src="{{ $file_link }}" alt="{{ $item->label }}" alt="Snow" style="width:100%;max-width:300px">
                                            </span>
                                        @else
                                            <span>
                                                @php
                                                    $file_info = get_file_basename_ext_from_link($file_link);
                                                @endphp
                                                <a href="{{ setRoute('file.download',["kyc-files",$item->value]) }}" >
                                                    {{ Str::substr($file_info->base_name ?? "", 0 , 20 ) ."..." . $file_info->extension ?? "" }}
                                                </a>
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __( $item->label ) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ (isset($item->value) && is_string($item->value)) ? $item->value : "" }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    <div class="preview-list-item">
                        <div class="preview-list-left">
                            <div class="preview-list-user-wrapper">
                                <div class="preview-list-user-icon">
                                    <i class="las la-money-check-alt"></i>
                                </div>
                                <div class="preview-list-user-content">
                                    <span class="last">{{ __('Total Payable Amount') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="preview-list-right">
                            <span class="text--info last payable">{{ get_amount($data->charges->total_payable, $data->charges->sender_currency) }}</span>
                        </div>
                    </div>
                </div>
                <div class="conformation-btn pt-4">
                    <form action="{{ setRoute('user.money-out.preview.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="temp_token" value="{{ $temp_data->identifier }}">
                        
                        <a href="javascript:void(0)" class="btn--base w-100 btn-loading" data-bs-toggle="modal" data-bs-target="#checkPin">{{ __('Confirm') }} <i class="las la-chevron-right"></i></a>
                        @include('user.components.modal.pin-check')
                    </form>
                </div>
             </div>
        </div>
    </div>
</div>


@endsection

@push('script')
<script>
    $('.finalConfirmed').hide();
    $('.pinCheck').on('keyup',function(e){
        var url = "{{ route('user.check.pin') }}";
        var pin = $(this).val();
        var token = '{{ csrf_token() }}';
        if ($(this).attr('name') == 'pin') {
            var data = {pin:pin,_token:token}
        }
        $.post(url,data,function(response) {
            if(response == 1){
                if($('.exist_pi').hasClass('text--danger')){
                    $('.exist_pi').removeClass('text--danger');
                }
                $('.exist_pi').text(`{{ __('Pin matched successfully.') }}`).addClass('text--success');
                $('.finalConfirmed').show();
                $('.finalConfirmed').attr('disabled',false)
            } else {

                if($('.exist_pi').hasClass('text--success')){
                    $('.exist_pi').removeClass('text--success');
                }
                $('.exist_pi').text('{{ __("Your entered pin does not matched.") }}').addClass('text--danger');
                $('.finalConfirmed').attr('disabled',true)
                $('.finalConfirmed').hide();
                return false
            }

        });
    });
</script>
<script>
    // Get the modal
    var modal = document.getElementById("myModal");
    
    // Get the image and insert it inside the modal - use its "alt" text as a caption
    var img = document.getElementById("myImg");
    var modalImg = document.getElementById("img01");
    var captionText = document.getElementById("caption");
    img.onclick = function(){
      modal.style.display = "block";
      modalImg.src = this.src;
      captionText.innerHTML = this.alt;
    }
    
    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];
    
    // When the user clicks on <span> (x), close the modal
    span.onclick = function() { 
      modal.style.display = "none";
    }
    </script>
@endpush
