<?php

namespace App\Http\Controllers\User;

use Exception;
use Carbon\Carbon;
use App\Models\UserWallet;
use App\Models\Transaction;
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
use Illuminate\Support\Facades\Auth;
use App\Models\StrowalletCustomerKyc;
use App\Models\StrowalletVirtualCard;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\User\VirtualCard\CardBuyNotification;
use App\Notifications\User\VirtualCard\CardFundNotification;

class StrowalletVirtualCardController extends Controller
{
    protected $api;
    protected $card_limit;
    protected $basic_settings;

    public function __construct(){
        $card_api               = VirtualCardApi::first();
        $this->api              = $card_api;
        $this->card_limit       = $card_api->card_limit;
        $this->basic_settings   = BasicSettings::first();
    }
    /**
     * Method for show virtual card page
     * @return view
     */
    public function index(){
        $page_title         = "Virtual Card";
        $myCards            = StrowalletVirtualCard::auth()->latest()->limit($this->card_limit)->get();
        $user               = auth()->user();
        $customer_email     = $user->strowallet_customer->customerEmail ?? '';

        if($customer_email === false){
            $customer_card = 0;
        }else{
            $customer_card = StrowalletVirtualCard::auth()->where('customer_email', $customer_email)->count();
        }
        $cardCharge         = TransactionSetting::where('slug',GlobalConst::TRX_VIRTUAL_CARD)->where('status',1)->first();
        $cardReloadCharge   = TransactionSetting::where('slug',GlobalConst::TRX_RELOAD_CARD)->where('status',1)->first();
        $cardApi            = $this->api;
        $card_limit         = $this->card_limit;
        $transactions       = Transaction::auth()->where('type',PaymentGatewayConst::TYPEVIRTUALCARD)->orderBy('id','desc')->latest()->take(3)->get();

        return view('user.sections.virtual-card-strowallet.index',compact(
            'page_title',
            'cardApi',
            'myCards',
            'card_limit',
            'cardCharge',
            'transactions',
            'customer_card',
            'cardReloadCharge',
        ));
    }
    /**
     * Method for strowallet card buy page
     */
    public function createPage(){
        $page_title = __("Create Virtual Card");
        $user       = userGuard()['user'];
        $cardCharge     = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
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
                    return back()->with(['error' => [$getCustomerInfo['message'] ?? __("Something went wrong! Please try again.")]]);
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

        return view('user.sections.virtual-card-strowallet.create',compact('page_title','user','cardCharge'));
    }
    /**
     * Method for strowallet create customer
     */
    public function createCustomer(Request $request){

        $validated = Validator::make($request->all(),[
            'first_name'        => ['required', 'string', 'regex:/^[^0-9\W]+$/'],
            'last_name'         => ['required', 'string', 'regex:/^[^0-9\W]+$/'],
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
        ])->validate();
        $user       = userGuard()['user'];
        $validated['phone'] = $user->full_mobile;

        try{
            if($user->strowallet_customer == null){
                if($request->hasFile("id_image_font")) {
                    $image = upload_file($validated['id_image_font'],'card-kyc-images');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'card-kyc-images');
                    $validated['id_image_font']     = $upload_image;
                }
                if($request->hasFile("user_image")) {
                    $image = upload_file($validated['user_image'],'card-kyc-images');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'card-kyc-images');
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
            return redirect()->route("user.strowallet.virtual.card.create")->with(['success' => [__('Customer has been created successfully.')]]);
        }catch(Exception $e){
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
    }
    /**
     * Method for strowallet edit customer
     */
    public function editCustomer(){
        $user = userGuard()['user'];
        if($user->strowallet_customer == null){
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        $page_title = __("Update Customer Kyc");
        $customer_kyc = StrowalletCustomerKyc::where('user_id',$user->id)->first();
        return view('user.sections.virtual-card-strowallet.edit',compact('page_title','user','customer_kyc'));
    }
    /**
     * Method for strowallet update customer
     */
    public function updateCustomer(Request $request){

        $validated = Validator::make($request->all(),[
            'first_name'        => ['required', 'string', 'regex:/^[^0-9\W]+$/'],
            'last_name'         => ['required', 'string', 'regex:/^[^0-9\W]+$/'],
            'id_image_font'     => "nullable|image|mimes:jpg,png,svg,webp",
            'user_image'        => "nullable|image|mimes:jpg,png,svg,webp",
        ], [
            'first_name.regex'  => __('The First Name field should only contain letters and cannot start with a number or special character.'),
            'last_name.regex'   => __('The Last Name field should only contain letters and cannot start with a number or special character.'),
        ])->validate();
        $user       = userGuard()['user'];

        try{
            if($user->strowallet_customer != null){
                $customer_kyc = StrowalletCustomerKyc::where('user_id',$user->id)->first();
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
                if( $updateCustomer['status'] == false){
                    return $this->apiErrorHandle($updateCustomer["message"]);
                }
                //get customer api response
                $customer = $user->strowallet_customer;
                $getCustomerInfo = get_customer($this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$updateCustomer['data']['customerId']);
                if( $getCustomerInfo['status'] == false){
                    return back()->with(['error' => [$getCustomerInfo['message'] ?? __("Something went wrong! Please try again.")]]);
                }
                $customer               = (array) $customer;
                $customer_status_info   =  $getCustomerInfo['data'];

                foreach ($customer_status_info as $key => $value) {
                    $customer[$key] = $value;
                }
                $user->strowallet_customer = (object) $customer;
                $user->save();

            }else{
                return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
            }
            return redirect()->back()->with(['success' => [__('Customer has been updated successfully.')]]);

        }catch(Exception $e){
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }

    }
    /**
     * Method for buy card
     * @param Illuminate\Http\Request $request
     */
    public function cardBuy(Request $request){
        $user = auth()->user();
        $request->validate([
            'card_amount'       => 'required|numeric|gt:0',
            'name_on_card'      => 'required|string|min:4|max:50',
        ]);
        $formData   = $request->all();

        $amount = $request->card_amount;
        $basic_setting = $this->basic_settings;
        $wallet = UserWallet::auth()->first();
        if(!$wallet){
            return back()->with(['error' => [__('User wallet not found')]]);
        }
        $cardCharge = TransactionSetting::where('slug',GlobalConst::TRX_VIRTUAL_CARD)->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default currency not found.')]]);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__("Please follow the transaction limit.")]]);
        }
        //charge calculations
        $fixedCharge    = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge   = $fixedCharge + $percent_charge;
        $payable        = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance.')]]);
        }
        $customer = $user->strowallet_customer;
        if(!$customer){
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        $customer_email = $user->strowallet_customer->customerEmail ?? false;
        if($customer_email === false){
            $customer_card  = 0;
        }else{
            $customer_card  = StrowalletVirtualCard::where('customer_email',$customer_email)->count();
        }

        if($customer_card >= $this->card_limit){
            return back()->with(['error' => [__("Sorry! You can not create more than")." ".$this->card_limit ." ".__("card using the same email address.")]]);
        }
        // for live code
        $created_card = create_strowallet_virtual_card($user,$request->card_amount,$customer,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url,$formData);

        if($created_card['status'] == false){
            return back()->with(['error' => [$created_card['message']]]);
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
            
        }catch(Exception $e){
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return redirect()->route("user.strowallet.virtual.card.index")->with(['success' => [__('Virtual Card Buy Successfully.')]]);
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
            throw new Exception(__("Data is not saved properly."));
        }
        return $id;
    }
    // update user wallet balance
    function updateWalletBalance($wallet,$available_balance){
        $wallet->update([
            'balance'   => $available_balance
        ]);
    }
    // save transaction device information
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
    /**
     * Method for card details information
     * @param Illuminate\Http\Request $request, $card_id
     */
    public function cardDetails(Request $request,$card_id){
        $page_title = __("Card Details");
        $myCard = StrowalletVirtualCard::where('card_id',$card_id)->first();
        if(!$myCard) return back()->with(['error' => [__("Something is wrong in your card")]]);
        if($myCard->card_status == 'pending'){
            $card_details   = card_details($card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
         
            if($card_details['status'] == false){
                return back()->with(['error' => [__("Your Card Is Pending! Please Contact With Admin.")]]);
            }

            $myCard->user_id                   = Auth::user()->id;
            $myCard->card_status               = $card_details['data']['card_detail']['card_status'];
            $myCard->card_number               = $card_details['data']['card_detail']['card_number'];
            $myCard->last4                     = $card_details['data']['card_detail']['last4'];
            $myCard->cvv                       = $card_details['data']['card_detail']['cvv'];
            $myCard->expiry                    = $card_details['data']['card_detail']['expiry'];
            $myCard->save();
        }
        $cardApi = $this->api;

        return view('user.sections.virtual-card-strowallet.details',compact(
            'page_title',
            'myCard',
            'cardApi'
        ));
    }
    /**
     * card freeze unfreeze
     */
    public function cardBlockUnBlock(Request $request) {
        $validator = Validator::make($request->all(),[
            'status'                    => 'required|boolean',
            'data_target'               => 'required|string',
        ]);
        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->safe()->all();
        if($request->status == 1){
            $card           = StrowalletVirtualCard::where('id',$request->data_target)->where('is_active',1)->first();
            $client         = new \GuzzleHttp\Client();
            $public_key     = $this->api->config->strowallet_public_key;
            $base_url       = $this->api->config->strowallet_url;
            
            $response = $client->request('POST', $base_url.'action/status/?action=freeze&card_id='.$card->card_id.'&public_key='.$public_key, [
            'headers' => [
                'accept' => 'application/json',
            ],
            ]);

            $result = $response->getBody();
            $data  = json_decode($result, true);

            if( isset($data['status']) ){
                $card->is_active = 0;
                $card->save();
                $success = ['success' => [__('Card Freeze successfully!')]];
                return Response::success($success,null,200);
            }else{
                $error = ['error' =>  [$data['message']]];
                return Response::error($error,null,400);
            }
        }else{
            $card           = StrowalletVirtualCard::where('id',$request->data_target)->where('is_active',0)->first();
            $client         = new \GuzzleHttp\Client();
            $public_key     = $this->api->config->strowallet_public_key;
            $base_url       = $this->api->config->strowallet_url;

            $response = $client->request('POST', $base_url.'action/status/?action=unfreeze&card_id='.$card->card_id.'&public_key='.$public_key, [
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);
            $result = $response->getBody();
            $data  = json_decode($result, true);
            if(isset($data['status'])){
                $card->is_active = 1;
                $card->save();
                $success = ['success' => [__('Card UnFreeze successfully!')]];
                return Response::success($success,null,200);
            }else{
                $error = ['error' =>  [$data['message']]];
                return Response::error($error,null,400);
            }
        }

    }
    public function makeDefaultOrRemove(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'        => "required|numeric",
        ])->validate();
        $user = auth()->user();
        $targetCard =  StrowalletVirtualCard::where('id',$validated['target'])->where('user_id',$user->id)->first();
        $withOutTargetCards =  StrowalletVirtualCard::where('id','!=',$validated['target'])->where('user_id',$user->id)->get();

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

        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return back()->with(['success' => [__('Status Updated Successfully!')]]);
    }
    /**
     * Card Fund
     */
    public function cardFundConfirm(Request $request){
        $request->validate([
            'id'            => 'required|integer',
            'fund_amount'   => 'required|numeric|gt:0',
        ]);
        $basic_setting  = BasicSettings::first();
        $user           = auth()->user();

        $myCard         =  StrowalletVirtualCard::where('user_id',$user->id)->where('id',$request->id)->first();
        if(!$myCard){
            return back()->with(['error' => [__("Something is wrong in your card.")]]);
        }
        
        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return back()->with(['error' => [__('User wallet not found!')]]);
        }
        $cardCharge     = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
        $baseCurrency   = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default currency not found.')]]);
        }
        $fixedCharge    = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge   = $fixedCharge + $percent_charge;
        $payable        = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance.')]]);
        }

        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;
        $mode           = $this->api->config->strowallet_mode ?? GlobalConst::SANDBOX;
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
        if(!empty($decodedResult['success'])  && $decodedResult['success'] == true){
            //added fund amount to card
            $myCard->balance += $amount;
            $myCard->save();
            $trx_id         = generateTrxString('transactions','trx_id','CF-',14);
            $transaction_id = $this->insertCardFund($trx_id,$user,$wallet,$amount,$myCard,$payable,$fixedCharge,$percent_charge,$total_charge);
            $this->createTransactionDeviceRecord($transaction_id);
            if($basic_setting->email_notification == true){
                $data = [
                    'trx_id'            => $trx_id,
                    'title'             => "Virtual Card (Fund Amount)",
                    'request_amount'    => $amount,
                    'total_charge'      => $total_charge,
                    'payable'           => $payable,
                    'request_currency'  => get_default_currency_code(),
                    'card_amount'       => $myCard->balance,
                    'card_pan'          => $myCard->card_number ?? "---- ----- ---- ----",
                    'status'            => 'Success',
                ];
                try{
                    Notification::route('mail',$user->email)->notify(new CardFundNotification($user,(object)$data));
                }catch(Exception $e){}
                                
            }
            user_notification_data_save($user->id,$type = PaymentGatewayConst::TYPEVIRTUALCARD,$title = "Virtual Card (Card Fund)",$transaction_id,$amount,$gateway = null,$currency = get_default_currency_code(),$message = "Card Fund Successful.");

            $this->notification($payable,$user,$type="Card Fund");
        }else{
            return redirect()->back()->with(['error' => [@$decodedResult['message'].' ,'.__('Please Contact With Administration.')]]);
        }
        return redirect()->back()->with(['success' => [__('Card Funded Successfully.')]]);
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
            throw new Exception(__("Something went wrong! Please try again."));
        }
        return $id;
    }
    /**
     * Transactions
     */
    public function cardTransaction($card_id) {
        $user = auth()->user();
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id', $card_id)->first();
        $page_title = __("Virtual Card Transaction");
        $id = $card->card_id;
        $emptyMessage  = 'No Transaction Found!';
        $start_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-12 month" ) );
        $end_date = date('Y-m-d');
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

        if(isset($result['success']) == true && $result['success'] == true ){
            $data =[
                'status'        => true,
                'message'       => "Card Details Retrieved Successfully.",
                'data'          => $result['response'],
            ];
        }else{
            $data =[
                'status'        => false,
                'message'       => $result['message'] ?? 'Something is wrong! Contact With Admin',
                'data'          => null,
            ];
        }
        return view('user.sections.virtual-card-strowallet.trx',compact('page_title','card','data'));
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
        return back()->with(['error' => [$errorString ?? __("Something went wrong! Please try again.")]]);
    }
}
