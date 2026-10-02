@extends('admin.layouts.master')

@push('css')
<style>
    
    #myImg {
      border-radius: 5px;
      cursor: pointer;
      transition: 0.3s;
    }
    
    #myImg:hover {opacity: 0.7;}
    
    /* The Modal (background) */
    .modal {
      display: none; /* Hidden by default */
      position: fixed; /* Stay in place */
      z-index: 1000; /* Sit on top */
      padding-top: 100px; /* Location of the box */
      left: 0;
      top: 0;
      width: 100%; /* Full width */
      height: 100%; /* Full height */
      overflow: auto; /* Enable scroll if needed */
      background-color: rgb(0,0,0); /* Fallback color */
      background-color: rgba(0,0,0,0.9); /* Black w/ opacity */
    }
    
    /* Modal Content (image) */
    .modal-content {
      margin: auto;
      display: block;
      width: 80%;
      max-width: 700px;
    }
    
    /* Caption of Modal Image */
    #caption {
      margin: auto;
      display: block;
      width: 80%;
      max-width: 700px;
      text-align: center;
      color: #ccc;
      padding: 10px 0;
      height: 150px;
    }
    
    /* Add Animation */
    .modal-content, #caption {  
      -webkit-animation-name: zoom;
      -webkit-animation-duration: 0.6s;
      animation-name: zoom;
      animation-duration: 0.6s;
    }
    
    @-webkit-keyframes zoom {
      from {-webkit-transform:scale(0)} 
      to {-webkit-transform:scale(1)}
    }
    
    @keyframes zoom {
      from {transform:scale(0)} 
      to {transform:scale(1)}
    }
    
    /* The Close Button */
    .close {
      position: absolute;
      top: 15px;
      right: 35px;
      color: #f1f1f1;
      font-size: 40px;
      font-weight: bold;
      transition: 0.3s;
    }
    
    .close:hover,
    .close:focus {
      color: #bbb;
      text-decoration: none;
      cursor: pointer;
    }
    
    /* 100% Image Width on Smaller Screens */
    @media only screen and (max-width: 700px){
      .modal-content {
        width: 100%;
      }
    }
</style>
@endpush

@section('page-title')
    @include('admin.components.page-title', ['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb', [
        'breadcrumbs' => [
            [
                'name' => __('Dashboard'),
                'url' => setRoute('admin.dashboard'),
            ],
        ],
        'active' => __('User Care'),
    ])
@endsection

@section('content')
    <div class="custom-card">
        <div class="card-header">
            <h6 class="title">{{ __("Edit KYC") }}</h6>
        </div>
        <div class="card-body">
            <form class="card-form">
                <div class="row align-items-center mb-10-none">
                    <div class="col-xl-4 col-lg-4 form-group">
                        <ul class="user-profile-list-three">
                            <li class="bg--base one">{{ __("Full Name") }}: <span>{{ $user->fullname ?? '' }}</span></li>
                            <li class="bg--info two">{{ __("Username") }}: <span>{{ "@".$user->username ?? '' }}</span></li>
                            <li class="bg--success three">{{ __("Email") }}: <span>{{ $user->email ?? '' }}</span></li>
                            <li class="bg--warning four">{{ __("Status") }}: <span>{{ $user->stringStatus->value ?? '' }}</span></li>
                            <li class="bg--danger five">{{ __("Last Login") }}: <span>{{ $user->lastLogin ?? '' }}</span></li>
                        </ul>
                    </div>
                    <div class="col-xl-4 col-lg-4 form-group">
                        <div class="user-profile-thumb">
                            <img src="{{ $user->userImage }}" alt="user">
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 form-group">
                        <ul class="user-profile-list">
                            <li class="bg--danger one">{{ __("State") }}: <span>{{ $user->address->state ?? "-" }}</span></li>
                            <li class="bg--warning two">{{ __("Phone Number") }}: <span>{{ $user->full_mobile }}</span></li>
                            <li class="bg--success three">{{ __("Zip/Postal") }}: <span>{{ $user->address->zip ?? "-" }}</span></li>
                            <li class="bg--info four">{{ __("City") }}: <span>{{ $user->address->city ?? "-" }}</span></li>
                            <li class="bg--base five">{{ __("Country") }}: <span>{{ $user->address->country ?? "-" }}</span></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="custom-card mt-15">
        <div class="card-header">
            <h6 class="title">{{ __("Information of Logs") }}</h6>
            <span class="{{ $user->kycStringStatus->class }}">{{ $user->kycStringStatus->value }}</span>
            @include('admin.components.link.custom',[
                'href'          => setRoute('admin.users.details',$user->username),
                'text'          => __("Profile"),
                'class'         => "btn btn--base",
                'permission'    => "admin.users.details",
            ])
        </div>
        <div class="card-body">
            @if ($user->kyc != null && $user->kyc->data != null)
                <ul class="product-sales-info">
                    @foreach ($user->kyc->data ?? [] as $item)
                        @if ($item->type == "file")
                            @php
                                $file_link = get_file_link("kyc-files",$item->value);
                            @endphp
                            <li>
                                <span class="kyc-title">{{ __($item->label) }}:</span>
                                @if ($file_link == false)
                                    <span>{{ __("File not found!") }}</span>
                                    @continue
                                @endif
                                
                                @if (its_image($item->value))
                                    <span class="product-sales-thumb">
                                        <img class="kyc-img" src="{{ $file_link }}" alt="{{ $item->label }}">
                                    </span>
                                @else
                                    <span class="text--danger">
                                        @php
                                            $file_info = get_file_basename_ext_from_link($file_link);
                                        @endphp
                                        <a href="{{ setRoute('file.download',["kyc-files",$item->value]) }}" >
                                            {{ Str::substr($file_info->base_name ?? "", 0 , 20 ) ."..." . $file_info->extension ?? "" }}
                                        </a>
                                    </span>
                                @endif
                            </li>
                        @else
                            <li>
                                <span class="kyc-title">{{ __($item->label) }}:</span> 
                                <span>{{ $item->value }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
                <div class="product-sales-btn">
                    @if ($user->kyc_verified != global_const()::VERIFIED)
                        @include('admin.components.button.custom',[
                            'type'          => "button",
                            'class'         => "approve-btn w-100",
                            'text'          => __("Approve"),
                            'permission'    => "admin.users.kyc.approve",
                        ])
                    @endif

                    @if ($user->kyc_verified != global_const()::REJECTED)
                        @include('admin.components.button.custom',[
                            'type'          => "button",
                            'class'         => "bg--danger reject-btn w-100",
                            'text'          => __("Reject"),
                            'permission'    => "admin.users.kyc.reject",
                        ])
                    @endif
                </div>
            @else
                <div class="alert alert-primary">{{ __("KYC Information not submitted yet") }}</div>
            @endif
        </div>
    </div>

    @include('admin.components.modals.kyc-reject',compact("user"))
    <!-- The Modal -->
    <div id="myModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="img01">
        <div id="caption"></div>
    </div>
@endsection

@push('script')
    <script>
        $(".approve-btn").click(function(){
            var actionRoute = "{{ setRoute('admin.users.kyc.approve',$user->username) }}";
            var target      = "{{ $user->username }}";
            var message     = `{{ __("Are you sure to approve") }} {{ "@" . $user->username }} {{ __("KYC information") }}.`;
            openDeleteModal(actionRoute,target,message,"Approve","POST");
        });
    </script>
    <script>
        // Get the modal
        var modal = document.getElementById("myModal");
        var modalImg = document.getElementById("img01");
        var captionText = document.getElementById("caption");
    
        // Add event listener to all images with the class 'kyc-img'
        document.querySelectorAll(".kyc-img").forEach(function(img) {
            img.onclick = function() {
                modal.style.display = "block";
                modalImg.src = this.src;
                captionText.innerHTML = this.alt;
            };
        });
    
        // Get the <span> element that closes the modal
        var span = document.getElementsByClassName("close")[0];
    
        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        };
    </script>
@endpush
