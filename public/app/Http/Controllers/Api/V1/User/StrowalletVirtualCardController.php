<?php

namespace App\Http\Controllers\Api\V1\User;

use Exception;
use Carbon\Carbon;
use App\Models\UserWallet;
use Illuminate\Support\Arr;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\VirtualCardApi;
use App\Models\StrowalletCustomerKyc;
use App\Models\StrowalletVirtualCard;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\VirtualCard\CardBuyNotification;
use App\Notifications\User\VirtualCard\CardFundNotification;

class StrowalletVirtualCardController extends Controller{
    protected $api;
    protected $card_limit;
    protected $basic_settings;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
        $this->card_limit =  $cardApi->card_limit;
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index()
    {
        $user = auth()->user();
        $basic_settings = BasicSettings::first();
        $card_basic_info = [
            'card_create_limit' => @$this->api->card_limit,
            'card_back_details' => @$this->api->card_details,
            'card_bg' => get_image(@$this->api->image,'card-api'),
            'site_title' =>@$basic_settings->site_name,
            'site_logo' =>get_logo(@$basic_settings,'dark'),
            'site_fav' =>get_fav($basic_settings,'dark'),
        ];
        $myCards = StrowalletVirtualCard::where('user_id',$user->id)->latest()->limit($this->card_limit)->get()->map(function($data){
            $live_card_data = card_details($data->card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);

            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];
            return[
                'id' => $data->id,
                'name' => $data->name_on_card,
                'card_number'       => $data->card_number ?? '',
                'card_id'           => $data->card_id,
                'expiry'            => $data->expiry ?? '',
                'cvv'               => $data->cvv ?? '',
                'card_status'       => $data->card_status,
                'balance'           =>  getAmount(updateStroWalletCardBalance(auth()->user(),$data->card_id,$live_card_data),2),
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'site_fav' =>get_fav($basic_settings,'dark'),
                'status' => $data->is_active,
                'is_default' => $data->is_default,
                'status_info' =>(object)$statusInfo ,
            ];
        });
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){

            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();
        
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
            return[
                'balance' => getAmount($data->balance,2),
                'currency' => get_default_currency_code(),
            ];
        })->first();
        $customer_email = $user->strowallet_customer->customerEmail??false;
        if($customer_email === false){
            $customer_card  = 0;
        }else{
            $customer_card  = StrowalletVirtualCard::where('customer_email',$customer_email)->count();
        }
        $data =[
            'base_curr' => get_default_currency_code(),
            'rate'      => floatval(get_default_currency_rate()),
            'card_create_action' => $customer_card <  $this->card_limit ? true : false,
            'strowallet_customer_info' =>$user->strowallet_customer === null ? true : false,
            'card_basic_info' =>(object) $card_basic_info,
            'myCards'=> $myCards,
            'user'=>   $user,
            'userWallet'=>  (object)$userWallet,
            'cardCharge'=>(object)$cardCharge,
        ];
        $message =  ['success'=>[__('Virtual Card')]];
        return Response::success([__("Virtual Card")],[
            'data'      => $data
        ],200);
    }
    //charge
    public function charges(){
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){
            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();

        $data =[
            'base_curr' => get_default_currency_code(),
            'cardCharge'=>(object)$cardCharge
        ];
        return Response::success([__("Fees and Charges")],[
            'data'      => $data
        ],200);

    }
    //card details
    public function cardDetails(){
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            return Response::error([$validator->errors()->all(),[]]);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $myCard = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$myCard){
            return Response::error([__('Something is wrong in your card')],[],400);
        }
        if($myCard->card_status == 'pending'){
            $card_details   = card_details($card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);

            if($card_details['status'] == false){
                return Response::error([__("Your Card Is Pending! Please Contact With Admin")],[],400);
            }

            $myCard->user_id                   = $user->id;
            $myCard->card_status               = $card_details['data']['card_detail']['card_status'];
            $myCard->card_number               = $card_details['data']['card_detail']['card_number'];
            $myCard->last4                     = $card_details['data']['card_detail']['last4'];
            $myCard->cvv                       = $card_details['data']['card_detail']['cvv'];
            $myCard->expiry                    = $card_details['data']['card_detail']['expiry'];
            $myCard->save();
        }
        $myCards = StrowalletVirtualCard::where('card_id',$card_id)->where('user_id',$user->id)->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            return[
                'id' => $data->id,
                'name' => $data->name_on_card,
                'card_id' => $data->card_id,
                'card_number'       => $data->card_number ?? '',
                'card_brand' => $data->card_brand,
                'card_user_id' => $data->card_user_id,
                'expiry' => $data->expiry,
                'cvv' => $data->cvv,
                'card_type' => ucwords($data->card_type),
                'city' => $data->user->strowallet_customer->city??"",
                'state' => $data->user->strowallet_customer->state??"",
                'zip_code' => $data->user->strowallet_customer->zipCode??"",
                'amount' => getAmount($data->balance,2),
                'card_back_details' => @$this->api->card_details,
                'card_bg' => get_image(@$this->api->image,'card-api'),
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'status' => $data->is_active,
                'is_default' => $data->is_default,
            ];
        });
        
        $business_address =[
            [
                'id' => 1,
                'label_name' => __("Billing Country"),
                'value' => "United State",
            ],
            [
                'id' => 2,
                'label_name' => __("Billing City"),
                'value' => "Miami",
            ],
            [
                'id' => 3,
                'label_name' => __("Billing State"),
                'value' => "3401 N. Miami, Ave. Ste 230",
            ],
            [
                'id' => 4,
                'label_name' => __("Billing Zip Code"),
                'value' => "33127",
            ],

        ];
        $data =[
            'base_curr' => get_default_currency_code(),
            'myCards'=> $myCards,
            'business_address'=> $business_address,
        ];
        return Response::success([__("Card details data fetch successfully.")],[
            'data'  => $data
        ],200);
    }
    public function makeDefaultOrRemove(Request $request) {
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $validated = $validator->validate();
        $user = auth()->user();
        $targetCard =  StrowalletVirtualCard::where('card_id',$validated['card_id'])->where('user_id',$user->id)->first();
        if(!$targetCard){
            return Response::error([__("Something is wrong in your card")],[],400);
        };
        $withOutTargetCards =  StrowalletVirtualCard::where('id','!=',$targetCard->id)->where('user_id',$user->id)->get();
        try{
            $targetCard->update([
                'is_default'         => $targetCard->is_default ? 0 : 1,
            ]);
            if(isset(  $withOutTargetCards)){
                foreach(  $withOutTargetCards as $card){
                    $card->is_default = false;
                    $card->save();
                }
            }
            
            return Response::success([__('Status Updated Successfully')],[],200);

        }catch(Exception $e) {
            return Response::error([__("Something went wrong! Please try again.")],[],400);
        }
    }
    // card transactions
    public function cardTransaction() {
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            return Response::error([__("Something is wrong in your card")],[],400);
        }

        $curl = curl_init();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        curl_setopt_array($curl, [
        CURLOPT_URL => $base_url . "card-transactions/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'public_key' => $public_key,
            'card_id' => $card->card_id,
        ]),
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "content-type: application/json"
        ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $result  = json_decode($response, true);


        if( isset($result['success']) == true && $result['success'] == true ){
            return Response::success([__('Virtual Card Transaction')],[$result['response']],200);
        }else{
            $result['response']  = [
                'card_transactions' => []
            ];
            return Response::success([__('Virtual Card Transaction')],[$result['response']],200);
        }

    }
    //card block
    public function cardBlock(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $card_id = $request->card_id;
        $user = auth()->user();
        $status = 'freeze';
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            return Response::error([__("Something is wrong in your card")],[],400);
        }
        if($card->is_active == false){
            return Response::error([__('Sorry,This Card Is Already Freeze')],[],400);
        }
        
        $client = new \GuzzleHttp\Client();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;
        $response = $client->request('POST', $base_url.'action/status/?action='.$status.'&card_id='.$card->card_id.'&public_key='.$public_key, [
        'headers' => [
            'accept' => 'application/json',
        ],
        ]);
        
        $result = $response->getBody();
        $data  = json_decode($result, true);
        if (isset($data)) {
            if (isset($data['status']) && $data['status'] == 'true') {
                $card->is_active = 0;
                $card->save();
                return Response::success([__('Card Freeze successfully')],[],200);
                
            }else{
                return Response::error([__("Something went wrong! Please try again.")],[],400);
            }
        }else{
            return Response::error([$data['error']??__("Something went wrong! Please try again.")],[],400);
        }

    }
    //unblock card
    public function cardUnBlock(Request $request){
        $validator  = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $card_id    = $request->card_id;
        $user       = auth()->user();
        $status     = 'unfreeze';
        $card       = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            return Response::error([__("Something is wrong in your card")],[],400);
        }
        if($card->is_active == true){
            return Response::error([__('Sorry,This Card Is Already Unfreeze')],[],400);
        }
        $client         = new \GuzzleHttp\Client();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $response = $client->request('POST', $base_url.'action/status/?action='.$status.'&card_id='.$card->card_id.'&public_key='.$public_key, [
        'headers' => [
            'accept' => 'application/json',
        ],
        ]);

        $result = $response->getBody();
        $data  = json_decode($result, true);

        if (isset($data['status'])) {
            $card->is_active = 1;
            $card->save();
            return Response::success([__('Card UnFreeze successfully')],[],200);
        }else{
            return Response::error([$data['message']],[],400);
        }

    }
    public function createPage(){
        $user       = auth()->user();
        $customer_exist_status  = $user->strowallet_customer != null ? true : false;
        $customer_create_fields =  [
            [
                'id'    => 1,
                'field_name' => "first_name",
                'label_name' => __("First Name"),
                'site_label' => __("Should match with your ID"),
                'type'       => 'text',
                'required'  => true
            ],
            [
                'id'    => 2,
                'field_name' => "last_name",
                'label_name' => __("Last Name"),
                'site_label' => __("Should match with your ID"),
                'type'       => 'text',
                'required'  => true
            ],

            [
                'id'    => 3,
                'field_name' => "phone_code",
                'label_name' => __("Phone Code"),
                'site_label' => "",
                'type'       => 'number',
                'required'  => true
            ],
            [
                'id'    => 4,
                'field_name' => "phone",
                'label_name' => __("Phone"),
                'site_label' => "",
                'type'       => 'number',
                'required'  => true
            ],
            [
                'id'    => 5,
                'field_name' => "customer_email",
                'label_name' => __("Email"),
                'site_label' => "",
                'type'       => 'email',
                'required'  => true
            ],

            [
                'id'    => 6,
                'field_name' => "date_of_birth",
                'label_name' => __("Date Of Birth"),
                'site_label' =>__("Should match with your ID"),
                'type'       => 'date',
                'required'   => true
            ],
            [
                'id'    => 7,
                'field_name' => "house_number",
                'label_name' => __("House Number"),
                'site_label' => "",
                'type'       => 'text',
                'required'   => true
            ],
            [
                'id'    => 8,
                'field_name' => "address",
                'label_name' => __("Address"),
                'site_label' => "",
                'type'       => 'text',
                'required'   => true
            ],
            [
                'id'    => 9,
                'field_name' => "zip_code",
                'label_name' => __("Zip Code"),
                'site_label' => "",
                'type'       => 'text',
                'required'   => true
            ],
            [
                'id'    => 10,
                'field_name' => "id_image_font",
                'label_name' => __("ID Card Image (Font Side)"),
                'site_label' => __("NID/Passport"),
                'type'       => 'file',
                'required'   => true
            ],
            [
                'id'    => 11,
                'field_name' => "user_image",
                'label_name' => __("Your Photo"),
                'site_label' => __("Should show your face and must be match with your ID"),
                'type'       => 'file',
                'required'   => true
            ],
        ];
        if($user->strowallet_customer){
            $customer_exist =  $user->strowallet_customer;
        }else{
            $customer_exist =[
                "customerEmail"     => "",
                "firstName"         => "",
                "lastName"          => "",
                "phoneNumber"       => "",
                "city"              => "",
                "state"             => "",
                "country"           => "",
                "line1"             => "",
                "zipCode"           => "",
                "houseNumber"       => "",
                "idNumber"          => "",
                "idType"            => "",
                "idImage"           => "",
                "userPhoto"         => "",
                "customerId"        => "",
                "dateOfBirth"       => "",
                "status"            => ""
            ];
            $customer_exist = (object) $customer_exist;
        }

        $customer_kyc_status_can_be    = [
            'low kyc',
            'unreview kyc',
            'high kyc',
        ];
        $customer_kyc_status    = $customer_exist->status ?? "";
        $customer_low_kyc_text  = __("Thank you for submitting your KYC information. Your details are currently under review. We will notify you once the verification is complete. Please note that the creation of your virtual card will proceed after your KYC is approved.");
        $card_create_fields =  [
            [
                'id'    => 1,
                'field_name' => "name_on_card",
                'label_name' => __("Card Holder's Name"),
                'site_label' => "",
                'type'       => 'text',
                'required'   => true
            ],
            [
                'id'    => 2,
                'field_name' => "card_amount",
                'label_name' =>__("Amount"),
                'site_label' => "",
                'type'       => 'number',
                'required'   => true
            ],
            [
                'id'    => 3,
                'field_name' => "currency",
                'label_name' =>__("Select Currency"),
                'site_label' => "",
                'type'       => 'select',
                'required'   => true
            ],
        ];
        $data =[
            'customer_exist_status'     => $customer_exist_status,
            'customer_create_fields'    => (array)$customer_create_fields,
            'customer_exist'            => $customer_exist,
            'customer_kyc_status_can_be'=> $customer_kyc_status_can_be,
            'customer_kyc_status'       => $customer_kyc_status,
            'customer_low_kyc_text'     => $customer_low_kyc_text,
            'card_create_fields'        => $card_create_fields,
        ];
        return Response::success([__('Data Fetch Successful')],[
            'data'      => $data
        ],200);

    }
    public function updateCustomerStatus(){
        $user       = auth()->user();
        if($user->strowallet_customer != null){
            //get customer api response
            $customer = $user->strowallet_customer;
            if (!isset($customer->customerId) || empty($customer->customerId)) {
                $customer   = (array) $customer;
                $customer['status'] = GlobalConst::CARD_HIGH_KYC_STATUS;
                $user->strowallet_customer = (object) $customer;
                $user->save();
            }else{
                $getCustomerInfo = get_customer($this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$customer->customerId);
                if( $getCustomerInfo['status'] == false){
                    return Response::error([$getCustomerInfo['message'] ?? __("Something went wrong! Please try again.")],[],400);
                    
                }
                $customer               = (array) $customer;
                $customer_status_info   =  $getCustomerInfo['data'];

                foreach ($customer_status_info as $key => $value) {
                    $customer[$key] = $value;
                }
                $user->strowallet_customer = (object) $customer;
                $user->save();
            }
        }
        return Response::success([__('Customer Status Updated Successfully.')],[],200);

    }
    public function createCustomer(Request $request){
        $validator = Validator::make($request->all(), [
            'first_name'        => ['required', 'string', 'regex:/^[^0-9\W]+$/'], // First name validation
            'last_name'         => ['required', 'string', 'regex:/^[^0-9\W]+$/'],  // Last name validation
            'customer_email'    => 'required|email',
            'date_of_birth'     => 'required|string',
            'house_number'      => 'required|string',
            'address'           => 'required|string',
            'zip_code'          => 'required|string',
            'id_image_font'     => "required|image|mimes:jpg,png,svg,webp",
            'user_image'        => "required|image|mimes:jpg,png,svg,webp",
        ], [
            'first_name.regex'  => __('The First Name field should only contain letters and cannot start with a number or special character.'),
            'last_name.regex'   => __('The Last Name field should only contain letters and cannot start with a number or special character.'),
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all(),[]);
            
        }
        $validated = $validator->validate();
        $user       = auth()->user();
        $validated['phone'] = $user->full_mobile;
        try{
            if($user->strowallet_customer == null){

                if($request->hasFile("id_image_font")) {
                    $image = upload_file($validated['id_image_font'],'card-kyc-images');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'card-kyc-images');
                    delete_file($image['dev_path']);
                    $validated['id_image_font']     = $upload_image;
                }

                //user image
                if($request->hasFile("user_image")) {
                    $image = upload_file($validated['user_image'],'card-kyc-images');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'card-kyc-images');
                    delete_file($image['dev_path']);
                    $validated['user_image']     = $upload_image;
                }
                $exist_kyc = StrowalletCustomerKyc::where('user_id',$user->id)->first();
                if($exist_kyc){
                    $exist_kyc->update([
                        'user_id'         =>  $user->id,
                        'face_image'      =>  $validated['user_image'],
                        'id_image'        =>  $validated['id_image_font']
                    ]);
                    $kyc_info = StrowalletCustomerKyc::where('user_id',$user->id)->first();
                }else{
                    //store kyc images
                    $kyc_info = StrowalletCustomerKyc::create([
                        'user_id'         =>  $user->id,
                        'face_image'      =>  $validated['user_image'],
                        'id_image'        =>  $validated['id_image_font']
                    ]);
                }

                $idImage = $kyc_info->idImageData;
                $userPhoto = $kyc_info->faceImageData;

                $validated = Arr::except($validated,['id_image_font','user_image']);
                $createCustomer     = stro_wallet_create_user($validated,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$idImage,$userPhoto);
                if( $createCustomer['status'] == false){
                    $kyc_info->delete();
                    return $this->apiErrorHandle($createCustomer["message"]);
                }
                $user->strowallet_customer =   (object)$createCustomer['data'];
                $user->save();
            }
            return Response::success([__('Customer has been created successfully.')],[],200);
            
        }catch(Exception $e){
            return Response::error([__("Something went wrong! Please try again.")],[],400);
        }


    }
    public function updateCustomer(Request $request){
        $validator = Validator::make($request->all(), [
            'first_name'        => ['required', 'string', 'regex:/^[^0-9\W]+$/'], // First name validation
            'last_name'         => ['required', 'string', 'regex:/^[^0-9\W]+$/'],  // Last name validation
            'id_image_font'     => "nullable|image|mimes:jpg,png,svg,webp",
            'user_image'        => "nullable|image|mimes:jpg,png,svg,webp",
        ], [
            'first_name.regex'  => __('The First Name field should only contain letters and cannot start with a number or special character.'),
            'last_name.regex'   => __('The Last Name field should only contain letters and cannot start with a number or special character.'),
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all(),[]);
        }
        $validated = $validator->validate();
        $user      = auth()->user();
        try{
            if($user->strowallet_customer != null){
                $customer_kyc = StrowalletCustomerKyc::where('user_id',$user->id)->first();
                if(!$customer_kyc){
                    return Response::error([__("No data found!")],[],400);
                }
                if($request->hasFile("id_image_font")) {
                    $id_image = upload_file($validated['id_image_font'],'card-kyc-images',);
                    $upload_image = upload_files_from_path_dynamic([$id_image['dev_path']],'card-kyc-images',$customer_kyc->id_image??null);
                    // delete_file($id_image['dev_path']);
                    $validated['id_image_font']     = $upload_image;
                }

                //user image
                if($request->hasFile("user_image")) {
                    $user_image = upload_file($validated['user_image'],'card-kyc-images',$customer_kyc->face_image??null);
                    $upload_image = upload_files_from_path_dynamic([$user_image['dev_path']],'card-kyc-images');
                    // delete_file($user_image['dev_path']);
                    $validated['user_image']     = $upload_image;
                }
                //store kyc images
                $customer_kyc->update([
                    'user_id'         =>  $user->id,
                    'id_image'        =>  $validated['id_image_font'] ?? $customer_kyc->id_image,
                    'face_image'      =>  $validated['user_image'] ??$customer_kyc->face_image
                ]);

                $idImage = $customer_kyc->idImageData;
                $userPhoto = $customer_kyc->faceImageData;

                $validated = Arr::except($validated,['id_image_font','user_image']);
                $updateCustomer     = update_customer($validated,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$idImage,$userPhoto,$user->strowallet_customer);
                if ($updateCustomer['status'] == false) {
                    return $this->apiErrorHandle($updateCustomer["message"]);

                }

                 //get customer api response
                $customer = $user->strowallet_customer;
                $getCustomerInfo = get_customer($this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$updateCustomer['data']['customerId']);
                if( $getCustomerInfo['status'] == false){
                    return Response::error([$getCustomerInfo['message'] ?? __("Something went wrong! Please try again.")],[],400);
                    
                }
                $customer               = (array) $customer;
                $customer_status_info   =  $getCustomerInfo['data'];

                foreach ($customer_status_info as $key => $value) {
                    $customer[$key] = $value;
                }
                $user->strowallet_customer = (object) $customer;
                $user->save();

            }else{
                return Response::error([__("Something went wrong! Please try again.")],[],400);
            }
            return Response::success([__('Customer has been updated successfully.')],[],200);
            
        }catch(Exception $e){
            return Response::error([__("Something went wrong! Please try again.")],[],400);
        }
    }

    //card buy
    public function cardBuy(Request $request){
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'name_on_card'      => 'required|string|min:4|max:50',
            'card_amount'       => 'required|numeric|gt:0',
        ]);

        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $formData   = $request->all();
        $amount = $request->card_amount;
        $basic_setting = BasicSettings::first();
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return Response::error([__('User wallet not found')],[],400);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return Response::error([__('Default currency not found')],[],400);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return Response::error([__('Please follow the transaction limit')],[],400);
        }
        //charge calculations
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return Response::error([__('Sorry, insufficient balance')],[],400);
        }

        $customer = $user->strowallet_customer;
        if(!$customer){
            return Response::error([__('Something went wrong! Please try again.')],[],400);
        }
        if($customer->status != GlobalConst::CARD_HIGH_KYC_STATUS){
            return Response::error([__('Your virtual card will proceed after your KYC is approved')],[],400);
        }

        $customer_email = $user->strowallet_customer->customerEmail??false;

        if($customer_email === false){
            $customer_card  = 0;
        }else{
            $customer_card  = StrowalletVirtualCard::where('customer_email',$customer_email)->count();
        }

        if($customer_card >= $this->card_limit){
            return Response::error([__("Sorry! You can not create more than")." ".$this->card_limit ." ".__("card using the same email address.")],[],400);
        }


        // for live code
        $created_card = create_strowallet_virtual_card($user,$request->card_amount,$customer,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$formData);
        if($created_card['status'] == false){
            return Response::error([$created_card['message']],[],400);
        }

        $strowallet_card                            = new StrowalletVirtualCard();
        $strowallet_card->user_id                   = $user->id;
        $strowallet_card->name_on_card              = $created_card['data']['name_on_card'];
        $strowallet_card->card_id                   = $created_card['data']['card_id'];
        $strowallet_card->card_created_date         = $created_card['data']['card_created_date'];
        $strowallet_card->card_type                 = $created_card['data']['card_type'];
        $strowallet_card->card_brand                = "visa";
        $strowallet_card->card_user_id              = $created_card['data']['card_user_id'];
        $strowallet_card->reference                 = $created_card['data']['reference'];
        $strowallet_card->card_status               = $created_card['data']['card_status'];
        $strowallet_card->customer_id               = $created_card['data']['customer_id'];
        $strowallet_card->customer_email            = $request->customer_email??$customer->customerEmail;
        $strowallet_card->balance                   = $amount;
        $strowallet_card->save();


        $trx_id = generateTrxString('transactions','trx_id','CB-',14);
        try{
            $transaction_id = $this->insertCardBuyInformation($trx_id,$user,$wallet,$amount,$strowallet_card,$payable,$fixedCharge,$percent_charge,$total_charge);

            $this->createTransactionDeviceRecord($transaction_id);

            if($basic_setting->email_notification == true){
                $data                   = [
                    'trx_id'            => $trx_id,
                    'title'             => "Virtual Card (Buy Card)",
                    'request_amount'    => $amount,
                    'total_charge'      => $total_charge,
                    'payable'           => $payable,
                    'request_currency'  => get_default_currency_code(),
                    'status'            => 'Success'
                ];
                try{
                    Notification::route('mail',$user->email)->notify(new CardBuyNotification($user,(object)$data));
                }catch(Exception $e){}
                
            }
            user_notification_data_save($user->id,$type = PaymentGatewayConst::TYPEVIRTUALCARD,$title = "Virtual Card (Card Buy)",$transaction_id,$amount,$gateway = null,$currency = get_default_currency_code(),$message = "Card Buy Successful.");
            $this->notification($payable,$user,$type = "Card Buy");
            return Response::success([__('Virtual Card Buy Successfully')],[],200);

        }catch(Exception $e){
            return Response::error([__('Something went wrong! Please try again.')],[],400);
        }

    }
    // function for save card buy information
    function insertCardBuyInformation($trx_id,$user,$wallet,$amount,$strowallet_card,$payable,$fixedCharge,$percent_charge,$total_charge){
        $available_balance  = $wallet->balance - $payable;
        $details            = [
            'card_info'     => $strowallet_card ?? '',
        ];
        DB::beginTransaction();
        try{
            $id                         = DB::table('transactions')->insertGetId([
                'type'                  => PaymentGatewayConst::TYPEVIRTUALCARD,
                'trx_id'                => $trx_id,
                'user_id'               => $user->id,
                'wallet_id'             => $wallet->id,
                'request_amount'        => $amount,
                'request_currency'      => get_default_currency_code(),
                'fixed_charge'          => $fixedCharge,
                'percent_charge'        => $percent_charge,
                'total_charge'          => $total_charge,
                'total_payable'         => $payable,
                'available_balance'     => $available_balance,
                'remark'                => PaymentGatewayConst::CARDBUY,
                'details'               => json_encode($details),
                'status'                => PaymentGatewayConst::STATUSSUCCESS,
                'attribute'             => GlobalConst::RECEIVED,
                'created_at'            => now()
            ]);
            
            $this->updateWalletBalance($wallet,$available_balance);
            DB::commit();
        }catch(Exception $e){
            DB::rollBack();
            return Response::error([__("Something went wrong. Please try again.")],[],400);
            
        }
        return $id;
    }
    // notification information 
    public function notification($payable,$user,$type){

        $notification_content_admin = [
            'title'         => "Virtual Card($type)",
            'message'       => "$type ".get_amount($payable).' '. get_default_currency_code(). "by " . $user->fullname,
            'time'          => Carbon::now()->diffForHumans(),
            'image'         => auth()->user()->userImage,
        ];
        AdminNotification::create([
            'type'      => NotificationConst::SIDE_NAV,
            'admin_id'  => 1,
            'message'   => $notification_content_admin,
        ]);
    }
    // create transaction device
    public function createTransactionDeviceRecord($transaction_id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();
        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $transaction_id,
                'ip'            => $client_ip,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    /**
     * Card Fund
     */
    public function cardFundConfirm(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'fund_amount' => 'required|numeric|gt:0',
        ]);
        if($validator->fails()){
            return Response::error($validator->errors()->all(),[]);
        }
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        $myCard =  StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$request->card_id)->first();

        if(!$myCard){
            return Response::error([__('Something is wrong in your card')],[],400);
        }

        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return Response::error([__('User wallet not found')],[],400);
        }
        $cardCharge = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return Response::error([__('Default currency not found')],[],400);
        }

        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return Response::error([__('Please follow the transaction limit')],[],400);
        }
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return Response::error([__('Sorry, insufficient balance')],[],400);
        }

        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;
        $mode           = $this->api->config->strowallet_mode??GlobalConst::SANDBOX;
        $form_params    = [
            'card_id'       => $myCard->card_id,
            'amount'        => $amount,
            'public_key'    => $public_key
        ];
        if ($mode === GlobalConst::SANDBOX) {
            $form_params['mode'] = "sandbox";
        }


        $client = new \GuzzleHttp\Client();

        $response               = $client->request('POST', $base_url.'fund-card/', [
            'headers'           => [
                'accept'        => 'application/json',
            ],
            'form_params'       => $form_params,
        ]);

        $result         = $response->getBody();
        $decodedResult  = json_decode($result, true);

        if(!empty($decodedResult['success'])  && $decodedResult['success'] == "success"){
            //added fund amount to card
            $myCard->balance += $amount;
            $myCard->save();
            $trx_id = generateTrxString('transactions','trx_id','CF-',14);
            $transaction_id = $this->insertCardFund($trx_id,$user,$wallet,$amount,$myCard,$payable,$fixedCharge,$percent_charge,$total_charge);
            $this->createTransactionDeviceRecord($transaction_id);
            if($basic_setting->email_notification == true){
                $data = [
                    'trx_id'  => $trx_id,
                     'title'  => __("Virtual Card (Fund Amount)"),
                    'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                    'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                    'charges'   => getAmount( $total_charge,2).' ' .get_default_currency_code(),
                    'card_amount'  => getAmount($myCard->balance,2).' ' .get_default_currency_code(),
                    'card_pan'  =>    $myCard->card_number,
                    'status'  => __("success"),
                ];
                try{
                    Notification::route('mail',$user->email)->notify(new CardFundNotification($user,(object)$data));
                }catch(Exception $e){}
            }

            user_notification_data_save($user->id,$type = PaymentGatewayConst::TYPEVIRTUALCARD,$title = "Virtual Card (Card Fund)",$transaction_id,$amount,$gateway = null,$currency = get_default_currency_code(),$message = "Card Fund Successful.");

            $this->notification($payable,$user,$type="Card Fund");

        }else{
            return Response::error([@$decodedResult['message'].' ,'.__('Please Contact With Administration.')],[],200);
        }
        return Response::success([__("Card Funded Successfully.")],[],200);

    }
    //card fund helper
    public function insertCardFund($trx_id,$user,$wallet,$amount,$myCard,$payable,$fixedCharge,$percent_charge,$total_charge) {
        $available_balance = ($wallet->balance - $payable);
        $details =[
            'card_info' =>   $myCard??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'type'                          => PaymentGatewayConst::TYPEVIRTUALCARD,
                'trx_id'                        => $trx_id,
                'user_id'                       => $user->id,
                'wallet_id'                     => $wallet->id,
                'request_amount'                => $amount,
                'request_currency'              => get_default_currency_code(),
                'fixed_charge'                  => $fixedCharge,
                'percent_charge'                => $percent_charge,
                'total_charge'                  => $total_charge,
                'total_payable'                 => $payable,
                'available_balance'             => $available_balance,
                'remark'                        => ucwords(PaymentGatewayConst::CARDFUND),
                'details'                       => json_encode($details),
                'status'                        => PaymentGatewayConst::STATUSSUCCESS,
                'attribute'                     => GlobalConst::SEND,
                'created_at'                    => now(),
            ]);
            $this->updateWalletBalance($wallet,$available_balance);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            return Response::error([__('Something went wrong! Please try again.')],[],400);
        }
        return $id;
    }
    // update user wallet balance
    function updateWalletBalance($wallet,$available_balance){
        $wallet->update([
            'balance'   => $available_balance
        ]);
    }    
    public function apiErrorHandle($apiErrors){
        $error = ['error' => []];
        if (isset($apiErrors)) {
            if (is_array($apiErrors)) {
                foreach ($apiErrors as $field => $messages) {
                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            $error['error'][] = $message;
                        }
                    } else {
                        $error['error'][] = $messages;
                    }
                }
            } else {
                $error['error'][] = $apiErrors;
            }
        }

        $errorMessages = array_map(function($message) {
            return rtrim($message, '.');
        }, $error['error']);

        $errorString = implode(', ', $errorMessages);
        $errorString .= '.';

        return Response::error([$errorString ?? __("Something went wrong! Please try again.")],[],400);
    }
}
