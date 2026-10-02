<?php
namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [

            /*
            |--------------------------------------------------------------------------
            | SECURITY
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'USER_LOGIN',
                'notify_for'=>0,
                'name'=>'Login Alert',
                'subject'=>'New Login Detected',

                'email'=>'
                    Hello [[user]],<br>
                    A new login was detected on your account.<br><br>

                    Device: [[device]]<br>
                    IP Address: [[ip]]<br>
                    Time: [[time]]<br><br>

                    If this was not you, secure your account immediately.
                ',

                'sms'=>'Login detected on your account. If this was not you secure your account.',

                'push'=>'New login detected on your account.',

                'in_app'=>'Your account was accessed from a new device.',

                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],

                'is_security'=>true
            ],


            [
                'template_key'=>'PASSWORD_CHANGED',
                'notify_for'=>0,
                'name'=>'Password Changed',
                'subject'=>'Your Password Was Changed',

                'email'=>'Hello [[user]], your password was successfully changed.',

                'sms'=>'Your password was changed successfully.',

                'push'=>'Password changed successfully.',

                'in_app'=>'Your password has been updated.',

                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],

                'is_security'=>true
            ],



            /*
            |--------------------------------------------------------------------------
            | WALLET
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'WALLET_CREATED',
                'notify_for'=>0,

                'name'=>'Wallet Created',

                'subject'=>'Your Wallet Is Ready',

                'email'=>'Hello [[user]], your wallet has been created successfully.',

                'sms'=>'Your wallet has been created successfully.',

                'push'=>'Your wallet is now active.',

                'in_app'=>'Wallet creation completed.',

                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ]
            ],



            /*
            |--------------------------------------------------------------------------
            | CREDIT / FUNDING
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'WALLET_CREDITED',

                'notify_for'=>0,

                'name'=>'Wallet Credited',

                'subject'=>'Wallet Credit Successful',

                'email'=>'
                    Hello [[user]],<br>

                    Your wallet has been credited.<br><br>

                    Amount: [[amount]]<br>
                    Balance: [[balance]]<br>
                    Reference: [[reference]]
                ',

                'sms'=>'Wallet credited with [[amount]]. Ref: [[reference]]',

                'push'=>'Your wallet was credited with [[amount]].',

                'in_app'=>'Wallet credit successful.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],


                'is_transactional'=>true
            ],



            /*
            |--------------------------------------------------------------------------
            | DEBIT
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'WALLET_DEBITED',

                'notify_for'=>0,

                'name'=>'Wallet Debited',

                'subject'=>'Wallet Debit Alert',

                'email'=>'
                    Hello [[user]],<br>

                    Your wallet was debited.<br><br>

                    Amount: [[amount]]<br>
                    Balance: [[balance]]<br>
                    Reference: [[reference]]
                ',

                'sms'=>'Your wallet was debited [[amount]]. Ref: [[reference]]',

                'push'=>'Your wallet was debited [[amount]].',

                'in_app'=>'Wallet debit completed.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],


                'is_transactional'=>true
            ],




            /*
            |--------------------------------------------------------------------------
            | TRANSFERS
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'TRANSFER_SUCCESS',

                'notify_for'=>0,

                'name'=>'Transfer Successful',

                'subject'=>'Transfer Completed',

                'email'=>'
                    Transfer completed successfully.<br>

                    Amount: [[amount]]<br>
                    Recipient: [[recipient]]<br>
                    Reference: [[reference]]
                ',

                'sms'=>'Transfer successful. Amount [[amount]]. Ref [[reference]]',

                'push'=>'Transfer completed successfully.',

                'in_app'=>'Your transfer was successful.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],


                'is_transactional'=>true
            ],



            [
                'template_key'=>'TRANSFER_FAILED',

                'notify_for'=>0,

                'name'=>'Transfer Failed',

                'subject'=>'Transfer Failed',

                'email'=>'Your transfer failed. Reference [[reference]]',

                'sms'=>'Transfer failed. Ref [[reference]]',

                'push'=>'Your transfer failed.',

                'in_app'=>'Transfer was unsuccessful.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],


                'is_transactional'=>true
            ],




            /*
            |--------------------------------------------------------------------------
            | CRYPTO
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'CRYPTO_DEPOSIT_CONFIRMED',

                'notify_for'=>0,

                'name'=>'Crypto Deposit Confirmed',

                'subject'=>'Crypto Deposit Confirmed',

                'email'=>'
                    Crypto deposit confirmed.<br>

                    Asset: [[asset]]<br>
                    Amount: [[amount]]<br>
                    Network: [[network]]<br>
                    Transaction Hash: [[tx_hash]]
                ',

                'sms'=>'Crypto deposit confirmed [[amount]] [[asset]]',

                'push'=>'Your crypto deposit has been confirmed.',

                'in_app'=>'Crypto deposit completed.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],

                'is_transactional'=>true
            ],



            [
                'template_key'=>'CRYPTO_WITHDRAWAL_COMPLETED',

                'notify_for'=>0,

                'name'=>'Crypto Withdrawal Completed',

                'subject'=>'Crypto Withdrawal Completed',

                'email'=>'
                    Withdrawal completed.<br>

                    Asset: [[asset]]<br>
                    Amount: [[amount]]<br>
                    Address: [[address]]
                ',

                'sms'=>'Crypto withdrawal completed [[amount]] [[asset]]',

                'push'=>'Crypto withdrawal completed.',

                'in_app'=>'Your crypto withdrawal is complete.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ],

                'is_transactional'=>true
            ],



            /*
            |--------------------------------------------------------------------------
            | KYC
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'KYC_APPROVED',

                'notify_for'=>0,

                'name'=>'KYC Approved',

                'subject'=>'Identity Verification Approved',

                'email'=>'Congratulations [[user]], your identity verification has been approved.',

                'sms'=>'Your identity verification was approved.',

                'push'=>'KYC approved.',

                'in_app'=>'Your account verification is complete.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ]
            ],



            [
                'template_key'=>'KYC_REJECTED',

                'notify_for'=>0,

                'name'=>'KYC Rejected',

                'subject'=>'Identity Verification Failed',

                'email'=>'Your verification was rejected. Reason: [[reason]]',

                'sms'=>'KYC verification failed.',

                'push'=>'Verification failed.',

                'in_app'=>'Please update your verification details.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>true,
                    'push'=>true,
                    'in_app'=>true
                ]
            ],



            /*
            |--------------------------------------------------------------------------
            | ADMIN
            |--------------------------------------------------------------------------
            */


            [
                'template_key'=>'ADMIN_HIGH_VALUE_TRANSACTION',

                'notify_for'=>1,

                'name'=>'High Value Transaction',

                'subject'=>'Large Transaction Alert',

                'email'=>'Transaction above threshold detected. Amount: [[amount]]',

                'push'=>'High value transaction detected.',

                'in_app'=>'Large transaction requires attention.',


                'status'=>[
                    'mail'=>true,
                    'sms'=>false,
                    'push'=>true,
                    'in_app'=>true
                ]
            ],

            /*
            |--------------------------------------------------------------------------
            | AIRTIME
            |--------------------------------------------------------------------------
            */


            [
                'template_key' => 'AIRTIME',

                'notify_for' => 0,

                'name' => 'Airtime Purchase Successful',

                'subject' => 'Your Airtime Purchase Was Successful',

                'email' => '
                    Hello [[user]],<br><br>

                    Your airtime purchase has been completed successfully.<br><br>

                    Network: [[network]]<br>
                    Recipient: [[recipient]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]<br><br>

                    Thank you for choosing Bitmonie.
                ',

                'sms' => '₦[[amount]] airtime sent to [[recipient]] on [[network]]. Ref: [[reference]].',

                'push' => 'Your ₦[[amount]] airtime purchase was successful.',

                'in_app' => 'You successfully purchased ₦[[amount]] airtime for [[recipient]].',

                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],

                'is_transactional' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | EPINS
            |--------------------------------------------------------------------------
            */

            [
                'template_key' => 'EPIN_PURCHASE_SUCCESS',
                'notify_for' => 0,
                'name' => 'E-pin Purchase Successful',
                'subject' => 'Your E-pin Purchase Was Successful',
                'email' => '
                    Hello [[user]],<br><br>
                    Your e-pin purchase has been completed successfully.<br><br>
                    Amount: ₦[[amount]]<br>
                    Quantity: [[qty]]<br>
                    Token/Pin: [[token]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]<br><br>
                    Thank you for your patronage.
                ',
                'sms' => 'E-pin purchase of ₦[[amount]] successful. Ref: [[reference]]',
                'push' => 'Your e-pin purchase was successful.',
                'in_app' => 'You successfully purchased [[qty]] e-pin(s).',
                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],
                'is_transactional' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | BET WALLET
            |--------------------------------------------------------------------------
            */

            [
                'template_key' => 'BET_WALLET_FUND_SUCCESS',
                'notify_for' => 0,
                'name' => 'Bet Wallet Funding Successful',
                'subject' => 'Betting Wallet Funded Successfully',
                'email' => '
                    Hello [[user]],<br><br>
                    Your betting wallet funding was successful.<br><br>
                    Customer Name: [[customer_name]]<br>
                    Customer ID: [[customer_id]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]<br><br>
                    Thank you for using our service.
                ',
                'sms' => 'Funded [[customer_name]] bet wallet with ₦[[amount]]. Ref: [[reference]]',
                'push' => 'Bet wallet funding of ₦[[amount]] was successful.',
                'in_app' => 'You successfully funded [[customer_name]] bet wallet with ₦[[amount]].',
                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],
                'is_transactional' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | INTERNET SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            [
                'template_key' => 'INTERNET_SUB_SUCCESS',
                'notify_for' => 0,
                'name' => 'Internet Subscription Successful',
                'subject' => 'Internet Subscription Purchase Successful',
                'email' => '
                    Hello [[user]],<br><br>
                    Your internet subscription plan was purchased successfully.<br><br>
                    Plan ID: [[plan_id]]<br>
                    Quantity: [[qty]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]<br><br>
                    Thank you for your patronage.
                ',
                'sms' => 'Internet subscription of ₦[[amount]] successful. Ref: [[reference]]',
                'push' => 'Your internet subscription purchase was successful.',
                'in_app' => 'You successfully purchased an internet subscription plan.',
                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],
                'is_transactional' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | INTERNATIONAL AIRTIME / DATA
            |--------------------------------------------------------------------------
            */

            [
                'template_key' => 'INT_AIRTIME_DATA_SUCCESS',
                'notify_for' => 0,
                'name' => 'International Airtime/Data Successful',
                'subject' => 'International Airtime/Data Purchase Successful',
                'email' => '
                    Hello [[user]],<br><br>
                    Your international bill purchase was successful.<br><br>
                    Country ISO: [[iso]]<br>
                    Account: [[account]]<br>
                    Amount: [[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]<br><br>
                    Thank you for choosing our service.
                ',
                'sms' => 'International bill purchase of [[amount]] to [[account]] successful. Ref: [[reference]]',
                'push' => 'Your international airtime/data purchase was successful.',
                'in_app' => 'International bill payment completed successfully for [[account]].',
                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],
                'is_transactional' => true,
            ],
            /*
|--------------------------------------------------------------------------
| P2P ADS
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'P2P_AD_CREATED',
    'notify_for' => 0,
    'name' => 'P2P Ad Created',
    'subject' => 'Your P2P Advertisement Has Been Created',

    'email' => '
        Hello [[user]],<br><br>

        Your [[type]] advertisement has been created successfully.<br><br>

        Asset: [[asset]]<br>
        Amount: [[amount]]<br>
        Price: [[price]]<br>
        Status: [[status]]<br><br>

        Your advertisement is awaiting approval before it becomes visible.
    ',

    'sms' => 'Your P2P [[type]] ad for [[asset]] has been created and is awaiting approval.',

    'push' => 'Your P2P advertisement has been created.',

    'in_app' => 'Your P2P advertisement has been submitted for approval.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

[
    'template_key' => 'P2P_AD_APPROVED',
    'notify_for' => 0,
    'name' => 'P2P Ad Approved',
    'subject' => 'Your P2P Advertisement Is Now Live',

    'email' => '
        Hello [[user]],<br><br>

        Your [[type]] advertisement for [[asset]] has been approved.

        Your advertisement is now visible to other traders.
    ',

    'sms' => 'Your P2P advertisement has been approved.',

    'push' => 'Your advertisement is now online.',

    'in_app' => 'Your advertisement has been approved and is now live.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

[
    'template_key' => 'P2P_AD_REJECTED',
    'notify_for' => 0,
    'name' => 'P2P Ad Rejected',
    'subject' => 'Your P2P Advertisement Was Rejected',

    'email' => '
        Hello [[user]],<br><br>

        Unfortunately your advertisement was rejected.<br><br>

        Reason:<br>
        [[reason]]
    ',

    'sms' => 'Your P2P advertisement was rejected.',

    'push' => 'Your advertisement was rejected.',

    'in_app' => 'Your advertisement was rejected. Please review the reason.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],
],

[
    'template_key' => 'P2P_AD_CLOSED',
    'notify_for' => 0,
    'name' => 'P2P Ad Closed',
    'subject' => 'Your Advertisement Has Been Closed',

    'email' => '
        Hello [[user]],<br><br>

        Your P2P advertisement has been closed successfully.<br><br>

        Remaining reserved funds have been released back to your wallet where applicable.
    ',

    'sms' => 'Your P2P advertisement has been closed.',

    'push' => 'Advertisement closed successfully.',

    'in_app' => 'Your advertisement has been closed.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

/*
|--------------------------------------------------------------------------
| P2P ORDERS
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'P2P_ORDER_RECEIVED',
    'notify_for' => 0,
    'name' => 'New P2P Order Received',
    'subject' => 'New P2P Order Awaiting Your Approval',

    'email' => '
        Hello [[user]],<br><br>

        You have received a new P2P order on your advertisement.<br><br>

        <strong>Order Details</strong><br>
        Order ID: [[order]]<br>
        Buyer: [[buyer]]<br>
        Amount: [[amount]] [[asset]]<br>
        Price: [[price]]<br>
        Total: [[total]] [[fiat]]<br><br>

        <strong>Action Required</strong><br>
        Please review the order and approve or reject it before the order expires.<br><br>

        If you do not respond within the allowed time, the order may be cancelled automatically.
    ',

    'sms' => 'New P2P order received. Review and approve before it expires.',

    'push' => 'New P2P order awaiting your approval.',

    'in_app' => 'You have a new P2P order. Review and approve it before the timer expires.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

[
    'template_key' => 'P2P_PAYMENT_MARKED',
    'notify_for' => 0,
    'name' => 'Buyer Marked Payment',
    'subject' => 'Buyer Has Marked Payment As Sent',

    'email' => '
        Hello [[user]],<br><br>

        The buyer has marked payment as completed.<br><br>

        Please verify the payment before releasing the crypto.
    ',

    'sms' => 'Buyer has marked payment as sent.',

    'push' => 'Payment has been marked by the buyer.',

    'in_app' => 'Verify the payment and release the crypto if received.',

    'status' => [
        'mail' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

[
    'template_key' => 'P2P_CRYPTO_RELEASED',
    'notify_for' => 0,
    'name' => 'Crypto Released',
    'subject' => 'Crypto Successfully Released',

    'email' => '
        Hello [[user]],<br><br>

        The crypto for Order [[order]] has been released successfully.<br><br>

        Amount: [[amount]] [[asset]]
    ',

    'sms' => 'Crypto released successfully.',

    'push' => 'Crypto has been released.',

    'in_app' => 'Trade completed successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

[
    'template_key' => 'P2P_ORDER_CANCELLED',
    'notify_for' => 0,
    'name' => 'P2P Order Cancelled',
    'subject' => 'P2P Order Cancelled',

    'email' => '
        Hello [[user]],<br><br>

        Order [[order]] has been cancelled.<br><br>

        Any reserved funds have been returned where applicable.
    ',

    'sms' => 'P2P order cancelled.',

    'push' => 'Your P2P order has been cancelled.',

    'in_app' => 'Order cancelled successfully.',

    'status' => [
        'mail' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

[
    'template_key' => 'P2P_DISPUTE_OPENED',
    'notify_for' => 0,
    'name' => 'P2P Dispute Opened',
    'subject' => 'Dispute Opened For Your Trade',

    'email' => '
        Hello [[user]],<br><br>

        A dispute has been opened for Order [[order]].<br><br>

        Our support team will review the case shortly.
    ',

    'sms' => 'A dispute has been opened for your P2P trade.',

    'push' => 'Dispute opened.',

    'in_app' => 'Your trade is currently under dispute.',

    'status' => [
        'mail' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],
],

[
    'template_key' => 'P2P_DISPUTE_RESOLVED',
    'notify_for' => 0,
    'name' => 'P2P Dispute Resolved',
    'subject' => 'Your P2P Dispute Has Been Resolved',

    'email' => '
        Hello [[user]],<br><br>

        Your dispute for Order [[order]] has been resolved.<br><br>

        Resolution:<br>
        [[resolution]]
    ',

    'sms' => 'Your P2P dispute has been resolved.',

    'push' => 'Dispute resolved.',

    'in_app' => 'Your dispute has been resolved.',

    'status' => [
        'mail' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],
],

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'ADMIN_NEW_P2P_AD',
    'notify_for' => 1,
    'name' => 'New P2P Advertisement',
    'subject' => 'New P2P Advertisement Awaiting Approval',

    'email' => '
        Merchant: [[merchant]]<br>
        Type: [[type]]<br>
        Asset: [[asset]]<br>
        Amount: [[amount]]
    ',

    'push' => 'A new P2P advertisement requires approval.',

    'in_app' => 'A merchant has submitted a new advertisement.',

    'status' => [
        'mail' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],
],
/*
|--------------------------------------------------------------------------
| SAFEHAVEN BILLS
|--------------------------------------------------------------------------
*/


[
    'template_key' => 'AIRTIME_PURCHASE_SUCCESS',
    'notify_for' => 0,

    'name' => 'Airtime Purchase Successful',

    'subject' => 'Airtime Purchase Successful',

    'email' => '
        Hello [[user]],<br><br>

        Your airtime purchase was successful.<br><br>

        Network: [[network]]<br>
        Phone Number: [[phone]]<br>
        Amount: ₦[[amount]]<br>
        Reference: [[reference]]<br>
        Status: [[status]]
    ',

    'sms' => 'Airtime purchase of ₦[[amount]] successful for [[phone]]. Ref: [[reference]]',

    'push' => 'Your airtime purchase was successful.',

    'in_app' => 'You purchased ₦[[amount]] airtime successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],



[
    'template_key' => 'DATA_PURCHASE_SUCCESS',
    'notify_for' => 0,

    'name' => 'Data Purchase Successful',

    'subject' => 'Data Bundle Purchase Successful',

    'email' => '
        Hello [[user]],<br><br>

        Your data bundle purchase was successful.<br><br>

        Network: [[network]]<br>
        Phone Number: [[phone]]<br>
        Bundle: [[product]]<br>
        Amount: ₦[[amount]]<br>
        Reference: [[reference]]
    ',

    'sms' => 'Data purchase of ₦[[amount]] successful for [[phone]]. Ref: [[reference]]',

    'push' => 'Your data bundle purchase was successful.',

    'in_app' => 'Your data bundle has been activated successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],



[
    'template_key' => 'CABLE_PURCHASE_SUCCESS',
    'notify_for' => 0,

    'name' => 'Cable Subscription Successful',

    'subject' => 'Cable Subscription Successful',

    'email' => '
        Hello [[user]],<br><br>

        Your cable subscription was successful.<br><br>

        Provider: [[provider]]<br>
        Smart Card Number: [[smart_card]]<br>
        Package: [[product]]<br>
        Amount: ₦[[amount]]<br>
        Reference: [[reference]]
    ',

    'sms' => 'Cable subscription successful. Amount ₦[[amount]]. Ref: [[reference]]',

    'push' => 'Your cable subscription was successful.',

    'in_app' => 'Cable subscription completed successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],



[
    'template_key' => 'UTILITY_PAYMENT_SUCCESS',
    'notify_for' => 0,

    'name' => 'Utility Payment Successful',

    'subject' => 'Electricity Bill Payment Successful',

    'email' => '
        Hello [[user]],<br><br>

        Your electricity bill payment was successful.<br><br>

        Provider: [[provider]]<br>
        Meter Number: [[meter]]<br>
        Amount: ₦[[amount]]<br>
        Token: [[token]]<br>
        Reference: [[reference]]
    ',

    'sms' => 'Electricity payment of ₦[[amount]] successful. Ref: [[reference]]',

    'push' => 'Your electricity payment was successful.',

    'in_app' => 'Utility payment completed successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],



[
    'template_key' => 'BILL_PURCHASE_FAILED',
    'notify_for' => 0,

    'name' => 'Bill Purchase Failed',

    'subject' => 'Bill Payment Failed',

    'email' => '
        Hello [[user]],<br><br>

        Your bill payment could not be completed.<br><br>

        Service: [[service]]<br>
        Amount: ₦[[amount]]<br>
        Reason: [[reason]]<br>
        Reference: [[reference]]
    ',

    'sms' => 'Your bill payment failed. Ref: [[reference]]',

    'push' => 'Your bill payment failed.',

    'in_app' => 'Bill payment unsuccessful.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],
/*
|--------------------------------------------------------------------------
| SAFEHAVEN BILL EXTRA EVENTS
|--------------------------------------------------------------------------
*/


[
    'template_key' => 'BILL_PURCHASE_PENDING',
    'notify_for' => 0,

    'name' => 'Bill Purchase Pending',

    'subject' => 'Bill Payment Processing',

    'email' => '
        Hello [[user]],<br><br>

        Your bill payment is currently being processed.<br><br>

        Service: [[service]]<br>
        Provider: [[provider]]<br>
        Amount: ₦[[amount]]<br>
        Reference: [[reference]]
    ',

    'sms' => 'Your [[service]] payment is being processed. Ref: [[reference]]',

    'push' => 'Your bill payment is processing.',

    'in_app' => 'Your bill payment is pending confirmation.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],



[
    'template_key' => 'BILL_REFUND_SUCCESS',
    'notify_for' => 0,

    'name' => 'Bill Payment Refunded',

    'subject' => 'Bill Payment Refund Successful',

    'email' => '
        Hello [[user]],<br><br>

        Your bill payment has been refunded successfully.<br><br>

        Service: [[service]]<br>
        Amount Refunded: ₦[[amount]]<br>
        Reason: [[reason]]<br>
        Reference: [[reference]]
    ',

    'sms' => 'Refund of ₦[[amount]] completed. Ref: [[reference]]',

    'push' => 'Your bill payment refund has been completed.',

    'in_app' => 'Bill payment refund successful.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],



[
    'template_key' => 'BILL_VERIFICATION_SUCCESS',
    'notify_for' => 0,

    'name' => 'Customer Verification Successful',

    'subject' => 'Customer Verification Completed',

    'email' => '
        Hello [[user]],<br><br>

        Customer verification was successful.<br><br>

        Service: [[service]]<br>
        Customer Name: [[customer_name]]<br>
        Customer Number: [[customer_number]]
    ',

    'sms' => 'Customer verification successful for [[customer_number]].',

    'push' => 'Customer verification completed.',

    'in_app' => 'Customer details verified successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],
],



[
    'template_key' => 'BILL_VERIFICATION_FAILED',
    'notify_for' => 0,

    'name' => 'Customer Verification Failed',

    'subject' => 'Customer Verification Failed',

    'email' => '
        Hello [[user]],<br><br>

        Customer verification failed.<br><br>

        Service: [[service]]<br>
        Customer Number: [[customer_number]]<br>
        Reason: [[reason]]
    ',

    'sms' => 'Customer verification failed for [[customer_number]].',

    'push' => 'Customer verification failed.',

    'in_app' => 'Unable to verify customer details.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],
],



[
    'template_key' => 'BILL_TRANSACTION_ADMIN_ALERT',
    'notify_for' => 1,

    'name' => 'Bill Transaction Alert',

    'subject' => 'New Bill Transaction',

    'email' => '
        New bill transaction detected.<br><br>

        User: [[user]]<br>
        Service: [[service]]<br>
        Provider: [[provider]]<br>
        Amount: ₦[[amount]]<br>
        Reference: [[reference]]
    ',

    'push' => 'New bill payment completed.',

    'in_app' => 'A new bill transaction has been completed.',

    'status' => [
        'mail' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],
            /*
            |--------------------------------------------------------------------------
            | SAFEHAVEN BILLS
            |--------------------------------------------------------------------------
            */


            [
                'template_key' => 'AIRTIME_PURCHASE_SUCCESSFUL',
                'notify_for' => 0,

                'name' => 'Airtime Purchase Successful',

                'subject' => 'Airtime Purchase Successful',

                'email' => '
                    Hello [[user]],<br><br>

                    Your airtime purchase was successful.<br><br>

                    Network: [[network]]<br>
                    Phone Number: [[recipient]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]
                ',

                'sms' => 'Airtime purchase of ₦[[amount]] successful for [[recipient]]. Ref: [[reference]]',

                'push' => 'Your airtime purchase was successful.',

                'in_app' => 'You purchased ₦[[amount]] airtime successfully.',

                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],

                'is_transactional' => true,
            ],



            [
                'template_key' => 'DATA_PURCHASE_SUCCESSFUL',
                'notify_for' => 0,

                'name' => 'Data Purchase Successful',

                'subject' => 'Data Purchase Successful',

                'email' => '
                    Hello [[user]],<br><br>

                    Your data purchase was successful.<br><br>

                    Network: [[network]]<br>
                    Phone Number: [[recipient]]<br>
                    Product: [[product_id]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]
                ',

                'sms' => 'Data purchase of ₦[[amount]] successful for [[recipient]]. Ref: [[reference]]',

                'push' => 'Your data purchase was successful.',

                'in_app' => 'Your data bundle purchase was completed successfully.',

                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],

                'is_transactional' => true,
            ],



            [
                'template_key' => 'CABLE_PURCHASE_SUCCESSFUL',
                'notify_for' => 0,

                'name' => 'Cable TV Purchase Successful',

                'subject' => 'Cable TV Subscription Successful',

                'email' => '
                    Hello [[user]],<br><br>

                    Your cable TV subscription was successful.<br><br>

                    Provider: [[provider]]<br>
                    Smart Card Number: [[smart_card]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]
                ',

                'sms' => 'Cable subscription of ₦[[amount]] successful. Ref: [[reference]]',

                'push' => 'Your cable subscription was successful.',

                'in_app' => 'Your cable TV subscription has been completed.',

                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],

                'is_transactional' => true,
            ],



            [
                'template_key' => 'UTILITY_PURCHASE_SUCCESSFUL',
                'notify_for' => 0,

                'name' => 'Utility Payment Successful',

                'subject' => 'Electricity Payment Successful',

                'email' => '
                    Hello [[user]],<br><br>

                    Your utility payment was successful.<br><br>

                    Provider: [[provider]]<br>
                    Meter Number: [[meter_number]]<br>
                    Vend Type: [[vend_type]]<br>
                    Amount: ₦[[amount]]<br>
                    Reference: [[reference]]<br>
                    Status: [[status]]
                ',

                'sms' => 'Utility payment of ₦[[amount]] successful. Ref: [[reference]]',

                'push' => 'Your utility payment was successful.',

                'in_app' => 'Your utility bill payment has been completed.',

                'status' => [
                    'mail' => true,
                    'sms' => true,
                    'push' => true,
                    'in_app' => true,
                ],

                'is_transactional' => true,
            ],
            /*
|--------------------------------------------------------------------------
| USDT EASY EARN
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| EASY EARN INVESTMENT CREATED
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'EASY_EARN_INVESTMENT_CREATED',

    'notify_for' => 0,

    'name' => 'USDT EasyEarn Investment Created',

    'subject' => 'USDT EasyEarn Investment Started',

    'email' => '
        Hello [[user]],<br><br>

        Your USDT EasyEarn investment has been created successfully.<br><br>

        <strong>Investment Details</strong><br>
        Investment ID: [[investment_id]]<br>
        Amount: [[amount]] USDT<br>
        Interest Rate: [[interest_rate]]% monthly<br>
        Duration: [[duration]] months<br>
        Start Date: [[start_date]]<br>
        Maturity Date: [[end_date]]<br>
        Auto Compound: [[auto_compound]]<br><br>

        Your USDT has been securely locked for the duration of the investment and interest will be credited according to the EasyEarn terms.
    ',

    'sms' => 'USDT EasyEarn started. Amount: [[amount]] USDT. Rate: [[interest_rate]]%. Matures: [[end_date]].',

    'push' => 'Your USDT EasyEarn investment of [[amount]] USDT has started.',

    'in_app' => 'USDT EasyEarn investment of [[amount]] USDT created successfully.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


/*
|--------------------------------------------------------------------------
| EASY EARN INTEREST CREDITED
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'EASY_EARN_INTEREST_CREDITED',

    'notify_for' => 0,

    'name' => 'USDT EasyEarn Interest Credited',

    'subject' => 'USDT EasyEarn Interest Credited',

    'email' => '
        Hello [[user]],<br><br>

        Interest has been credited to your USDT EasyEarn investment.<br><br>

        <strong>Interest Details</strong><br>
        Investment ID: [[investment_id]]<br>
        Interest Credited: [[amount]] USDT<br>
        Days Credited: [[days]]<br>
        Auto Compound: [[auto_compound]]<br>
        Total Interest Earned: [[total_interest_earned]] USDT<br><br>

        Your EasyEarn investment continues to earn according to the applicable investment terms.
    ',

    'sms' => 'EasyEarn interest credited: [[amount]] USDT. Investment: [[investment_id]].',

    'push' => 'You earned [[amount]] USDT in EasyEarn interest.',

    'in_app' => '[[amount]] USDT EasyEarn interest has been credited to your investment.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


/*
|--------------------------------------------------------------------------
| EASY EARN INTEREST WITHDRAWN
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'EASY_EARN_INTEREST_WITHDRAWN',

    'notify_for' => 0,

    'name' => 'USDT EasyEarn Interest Withdrawn',

    'subject' => 'USDT EasyEarn Interest Withdrawal Successful',

    'email' => '
        Hello [[user]],<br><br>

        Your accrued USDT EasyEarn interest has been withdrawn successfully.<br><br>

        <strong>Withdrawal Details</strong><br>
        Investment ID: [[investment_id]]<br>
        Amount Withdrawn: [[amount]] USDT<br>
        Asset: [[asset]]<br><br>

        The withdrawn amount has been made available to your USDT EasyEarn wallet.
    ',

    'sms' => 'EasyEarn interest withdrawal successful. Amount: [[amount]] USDT.',

    'push' => 'Your EasyEarn interest withdrawal of [[amount]] USDT was successful.',

    'in_app' => 'You successfully withdrew [[amount]] USDT in EasyEarn interest.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


/*
|--------------------------------------------------------------------------
| EASY EARN PRINCIPAL WITHDRAWN
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'EASY_EARN_PRINCIPAL_WITHDRAWN',

    'notify_for' => 0,

    'name' => 'USDT EasyEarn Investment Matured',

    'subject' => 'USDT EasyEarn Investment Completed',

    'email' => '
        Hello [[user]],<br><br>

        Your USDT EasyEarn investment has reached maturity and the funds have been returned successfully.<br><br>

        <strong>Investment Details</strong><br>
        Investment ID: [[investment_id]]<br>
        Amount Returned: [[amount]] USDT<br>
        Asset: [[asset]]<br>
        Auto Compound: [[auto_compound]]<br><br>

        The matured USDT has been returned to your Quidax account.
    ',

    'sms' => 'EasyEarn investment matured. [[amount]] USDT has been returned to your account.',

    'push' => 'Your EasyEarn investment matured and [[amount]] USDT was returned.',

    'in_app' => 'Your EasyEarn investment has matured. [[amount]] USDT has been returned to your account.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


/*
|--------------------------------------------------------------------------
| EASY EARN TOP UP
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'EASY_EARN_TOP_UP',

    'notify_for' => 0,

    'name' => 'USDT EasyEarn Investment Top Up',

    'subject' => 'USDT EasyEarn Investment Topped Up',

    'email' => '
        Hello [[user]],<br><br>

        Your USDT EasyEarn investment has been topped up successfully.<br><br>

        <strong>Top Up Details</strong><br>
        Investment ID: [[investment_id]]<br>
        Top Up Amount: [[amount]] USDT<br>
        New Investment Amount: [[new_total_amount]] USDT<br>
        Asset: [[asset]]<br><br>

        The additional USDT has been locked in your EasyEarn investment.
    ',

    'sms' => 'EasyEarn top up successful. Added [[amount]] USDT. New total: [[new_total_amount]] USDT.',

    'push' => 'Your EasyEarn investment was topped up by [[amount]] USDT.',

    'in_app' => 'You added [[amount]] USDT to your EasyEarn investment. New total: [[new_total_amount]] USDT.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


/*
|--------------------------------------------------------------------------
| EASY EARN TERMINATED
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'EASY_EARN_TERMINATED',

    'notify_for' => 0,

    'name' => 'USDT EasyEarn Investment Terminated',

    'subject' => 'USDT EasyEarn Investment Terminated',

    'email' => '
        Hello [[user]],<br><br>

        Your USDT EasyEarn investment has been terminated before maturity.<br><br>

        <strong>Termination Details</strong><br>
        Investment ID: [[investment_id]]<br>
        Amount Before Termination: [[amount_before_termination]] USDT<br>
        Principal Returned: [[principal_returned]] USDT<br>
        Interest Earned: [[interest_earned]] USDT<br>
        Interest Forfeited: [[forfeited_interest]] USDT<br><br>

        Only the eligible principal amount has been returned to your Quidax account. Accrued/compounded interest was forfeited in accordance with the early termination terms.
    ',

    'sms' => 'EasyEarn investment terminated. [[principal_returned]] USDT returned. [[forfeited_interest]] USDT interest forfeited.',

    'push' => 'Your EasyEarn investment has been terminated. [[principal_returned]] USDT was returned.',

    'in_app' => 'EasyEarn investment terminated. [[principal_returned]] USDT returned and [[forfeited_interest]] USDT interest forfeited.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],
/*
|--------------------------------------------------------------------------
| CRYPTO CARD FUNDING
|--------------------------------------------------------------------------
*/

[
    'template_key' => 'CRYPTO_CARD_FUNDING_INITIATED',

    'notify_for' => 0,

    'name' => 'Crypto Card Funding Initiated',

    'subject' => 'Your Crypto Card Funding Is Being Processed',

    'email' => '
        Hello [[user]],<br><br>

        Your crypto card funding request has been initiated successfully.<br><br>

        <strong>Funding Details</strong><br>
        Card: ****[[card]]<br>
        Amount: [[currency]] [[amount]]<br>
        Reference: [[reference]]<br>
        Provider: [[provider]]<br>
        Status: [[status]]<br><br>

        Your funds are currently being processed. You will receive another
        notification once the card has been successfully funded.
    ',

    'sms' =>
        'Your crypto card funding of [[currency]] [[amount]] has been initiated. Ref: [[reference]]. You will be notified once completed.',

    'push' =>
        'Your crypto card funding of [[currency]] [[amount]] is being processed.',

    'in_app' =>
        'Crypto card funding of [[currency]] [[amount]] has been initiated and is being processed.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


[
    'template_key' => 'CRYPTO_CARD_FUNDING_SUCCESS',

    'notify_for' => 0,

    'name' => 'Crypto Card Funding Successful',

    'subject' => 'Your Crypto Card Has Been Funded',

    'email' => '
        Hello [[user]],<br><br>

        Your crypto card has been funded successfully.<br><br>

        <strong>Funding Details</strong><br>
        Card: ****[[card]]<br>
        Amount: [[currency]] [[amount]]<br>
        Reference: [[reference]]<br>
        Status: Completed<br><br>

        The funds are now available on your card.
    ',

    'sms' =>
        'Your crypto card has been funded with [[currency]] [[amount]]. Ref: [[reference]].',

    'push' =>
        'Your crypto card has been funded successfully.',

    'in_app' =>
        'Your crypto card was successfully funded with [[currency]] [[amount]].',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],


[
    'template_key' => 'CRYPTO_CARD_FUNDING_FAILED',

    'notify_for' => 0,

    'name' => 'Crypto Card Funding Failed',

    'subject' => 'Crypto Card Funding Failed',

    'email' => '
        Hello [[user]],<br><br>

        Unfortunately, we were unable to complete your crypto card funding request.<br><br>

        <strong>Funding Details</strong><br>
        Card: ****[[card]]<br>
        Amount: [[currency]] [[amount]]<br>
        Reference: [[reference]]<br>
        Status: Failed<br>
        Reason: [[reason]]<br><br>

        If any amount was held during the process, it will be handled according
        to the escrow and refund process.
    ',

    'sms' =>
        'Crypto card funding of [[currency]] [[amount]] failed. Ref: [[reference]]. Reason: [[reason]]',

    'push' =>
        'Your crypto card funding could not be completed.',

    'in_app' =>
        'Crypto card funding failed. Please review the transaction details.',

    'status' => [
        'mail' => true,
        'sms' => true,
        'push' => true,
        'in_app' => true,
    ],

    'is_transactional' => true,
],

        ];


        foreach($templates as $template)
        {
            NotificationTemplate::updateOrCreate(

                [
                    'template_key'=>$template['template_key'],
                    'notify_for'=>$template['notify_for'],
                ],

                $template
            );
        }
    }
}
