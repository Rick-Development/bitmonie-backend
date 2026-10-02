<?php

namespace App\Models;

use App\Constants\GlobalConst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\KycVerification;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    protected $appends = ['fullname','userImage','stringStatus','lastLogin','kycStringStatus'];
    protected $dates = ['deleted_at'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ["id"];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'login_pin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'firstname'           => 'string',
        'lastname'            => 'string',
        'username'            => 'string',
        'email'               => 'string',
        'mobile_code'         => 'string',
        'mobile'              => 'string',
        'full_mobile'         => 'string',
        'account_no'          => 'string',
        'login_pin'           => 'string',
        'pin_status'          => 'integer',
        'pin_code'            => 'string',
        'password'            => 'string',
        'refferal_id'         => 'integer',
        'image'               => 'string',
        'status'              => 'integer',
        'email_verified_at'   => 'datetime',
        'strowallet_customer' => 'object',
        'address'             => 'object',
        'email_verified'      => 'integer',
        'sms_verified'        => 'integer',
        'kyc_verified'        => 'integer',
        'ver_code'            => 'integer',
        'ver_code_send_at'    => 'datetime',
        'two_factor_verified' => 'integer',
        'two_factor_status'   => 'integer',
        'remember_token'      => 'string',
        'deleted_at'          => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'quidax_id'            => 'string',
        'quidax_sn'            => 'string',
        'quidax_display_name'  => 'string',
        'quidax_reference'     => 'string',
        'payscribe_customer_id' => 'string',
        'payscribe_tier' => 'string',
        'payscribe_customer_phone' => 'string',
        'payscribe_customer_country' => 'string',
        'busha_recipient_id' => 'string',
    ];

    public function scopeEmailUnverified($query)
    {
        return $query->where('email_verified', false);
    }

    public function scopeEmailVerified($query) {
        return $query->where("email_verified",true);
    }

    public function scopeKycVerified($query) {
        return $query->where("kyc_verified",GlobalConst::VERIFIED);
    }

    public function scopeKycUnverified($query)
    {
        return $query->whereNot('kyc_verified',GlobalConst::VERIFIED);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', false);
    }

    public function kyc()
    {
        return $this->hasOne(UserKycData::class);
    }

    public function getFullnameAttribute()
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getFullMobileAttribute($value)
    {
        $mobile = $value;
        if (empty($mobile) && !empty($this->mobile_code) && !empty($this->mobile)) {
            $mobile = $this->mobile_code . $this->mobile;
        }
        if (empty($mobile)) {
            $mobile = $this->mobile;
        }

        if ($mobile) {
            if (preg_match('/^234[789]\d{9}$/', $mobile)) {
                return '+' . $mobile;
            }
            if (preg_match('/^[789]\d{9}$/', $mobile)) {
                return '0' . $mobile;
            }
        }

        return $mobile;
    }

    public function wallet()
    {
        return $this->hasOne(UserWallet::class);
    }

    public function getUserImageAttribute() {
        $image = $this->image;

        if($image == null) {
            return files_asset_path('profile-default');
        }else if(filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }else {
            return files_asset_path("user-profile") . "/" . $image;
        }
    }

    public function passwordResets() {
        return $this->hasMany(UserPasswordReset::class,"user_id");
    }

    public function scopeGetSocial($query,$credentials) {
        return $query->where("email",$credentials);
    }

    public function getStringStatusAttribute() {
        $status = $this->status;
        $data = [
            'class' => "",
            'value' => "",
        ];
        if($status == GlobalConst::ACTIVE) {
            $data = [
                'class'     => "badge badge--success",
                'value'     => "Active",
            ];
        }else if($status == GlobalConst::BANNED) {
            $data = [
                'class'     => "badge badge--danger",
                'value'     => "Banned",
            ];
        }
        return (object) $data;
    }

    public function getKycStringStatusAttribute() {
        $status = $this->kyc_verified;
        $data = [
            'class' => "",
            'value' => "",
        ];
        if($status == GlobalConst::APPROVED) {
            $data = [
                'class'     => "badge badge--success",
                'value'     => "Verified",
            ];
        }else if($status == GlobalConst::PENDING) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => "Pending",
            ];
        }else if($status == GlobalConst::REJECTED) {
            $data = [
                'class'     => "badge badge--danger",
                'value'     => "Rejected",
            ];
        }else {
            $data = [
                'class'     => "badge badge--danger",
                'value'     => "Unverified",
            ];
        }
        return (object) $data;
    }

    public function loginLogs(){
        return $this->hasMany(UserLoginLog::class);
    }

    public function getLastLoginAttribute() {
        if($this->loginLogs()->count() > 0) {
            return $this->loginLogs()->get()->last()->created_at->format("H:i A, d M Y");
        }

        return "N/A";
    }

    public function scopeSearch($query,$data) {
        return $query->where(function($q) use ($data) {
            $q->where("username","like","%".$data."%");
        })->orWhere("email","like","%".$data."%")
        ->orWhere("full_mobile","like","%".$data."%");
    }

    public function scopeNotAuth($query) {
        $query->whereNot("id",auth()->user()->id);
    }

    public function wallets()
    {
        return $this->hasMany(UserWallet::class, 'user_id');
    }

    public function biometricDevices()
    {
        return $this->hasMany(UserBiometricDevice::class);
    }

    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccounts::class, 'user_id');
    }

    public function p2pUserStat()
    {
        return $this->hasOne(P2PUserStat::class, 'user_id');
    }

    public function p2pAds()
    {
        return $this->hasMany(P2PAd::class, 'user_id');
    }

    public function p2pOrders()
    {
        return $this->hasMany(P2POrder::class, 'maker_id');
    }

    public function p2pOrdersAsTaker()
    {
        return $this->hasMany(P2POrder::class, 'taker_id');
    }

    public function p2pPaymentMethods()
    {
        return $this->hasMany(P2PPaymentMethod::class, 'user_id');
    }

    public function easyearnWallet()
    {
        return $this->hasOne(UsdtEasyearnWallet::class, 'user_id');
    }

    public function easyEarnPlans()
    {
        return $this->hasMany(EasyEarnPlan::class, 'user_id');
    }

    public function easyEarnTransactions()
    {
        return $this->hasMany(EasyEarnTransaction::class, 'user_id');
    }

    public function walletLedger()
    {
        return $this->hasMany(WalletLedger::class, 'user_id');
    }

    public function walletWithdrawalRequests()
    {
        return $this->hasMany(WalletWithdrawalRequest::class, 'user_id');
    }

    public function commissionWallets()
    {
        return $this->hasMany(CommissionWallet::class, 'user_id');
    }

    public function sharedReferralCommissions()
    {
        return $this->hasMany(ReferralCommission::class, 'referrer_user_id');
    }

    public function merchantApplications()
    {
        return $this->hasMany(MerchantApplication::class, 'user_id');
    }
    public function inAppNotifications()
{
    return $this->hasMany(
        InAppNotification::class,
        'user_id'
    );
}
/**
 * Get the user's multi-currency wallets.
 */
public function multicurrencyWallets(): HasMany
{
    return $this->hasMany(
        MultiCurrencyWallet::class,
        'user_id'
    );
}
public function user_kyc(){
     return $this->hasOne(
        KycVerification::class,
        'user_id',
        'id'
    );
}

}
