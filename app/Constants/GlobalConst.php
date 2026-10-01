<?php

namespace App\Constants;

class GlobalConst {
    const USER_PASS_RESEND_TIME_MINUTE  = "1";

    const ACTIVE                        = true;
    const BANNED                        = false;
    const SUCCESS                       = true;
    const DEFAULT_TOKEN_EXP_SEC         = 3600;

    const VERIFIED                      = 1;
    const APPROVED                      = 1;
    const PENDING                       = 2;
    const REJECTED                      = 3;
    const DEFAULT                       = 0;

    const SEND                          = 'SEND';
    const RECEIVED                      = 'RECEIVED';

    const UNVERIFIED                    = 0;

    const USER                          = "USER";
    const ADMIN                         = "ADMIN";

    const TRANSFER                      = "transfer";
    const EXCHANGE                      = "exchange";
    const ADD                           = "add";
    const OUT                           = "out";
    const PAYMENT                       = "payment";
    const BENEFICIARY                   = 'beneficiary';
    const FUND_TRANSFER                 = 'fund-transfer';

    const INVEST_PROFIT_DAILY_BASIS     = "DAILY-BASIS";
    const INVEST_PROFIT_ONE_TIME        = "ONE-TIME";

    const RUNNING                       = 2;
    const COMPLETE                      = 1;
    const CANCEL                        = 3;

    const INVESTMENT                    = "INVESTMENT";
    const PROFIT                        = "PROFIT";

    const UNKNOWN                       = "UNKNOWN";
    const USEFUL_LINK_PRIVACY_POLICY    = "PRIVACY_POLICY";

    const TRX_OWN_BANK_TRANSFER         = "Own Bank Transfer";
    const TRX_OTHER_BANK_TRANSFER       = "Other Bank Transfer";
    const TRX_MOBILE_WALLET_TRANSFER    = 'Mobile Wallet Transfer';

    const TRX_VIRTUAL_CARD              = "virtual_card";
    const TRX_RELOAD_CARD               = "reload_card"; 

    const LIVE                          = 'live';
    const SANDBOX                       = 'sandbox';
    const TRX_ACCOUNT_NUMBER            = "account";
    const TRX_CREDIT_CARD               = "credit-card";

    const TRX_SUB_TYPE                  = ['account' => 'Account'];

    const PERSONAL_ACCOUNT              = "personal";
    const BUSINESS_ACCOUNT              = "business";

    const CARD_UNDER_STATUS             = "unreview kyc";
    const CARD_LOW_KYC_STATUS           = "low kyc";
    const CARD_HIGH_KYC_STATUS          = "high kyc";

    const SYSTEM_MAINTENANCE            = "system-maintenance";
}
