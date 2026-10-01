<?php

namespace Database\Seeders\User;

use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = array(
            array('id' => '1','firstname' => 'App','lastname' => 'Devs','username' => 'appdevs','email' => 'user@appdevs.net','mobile_code' => '880','mobile' => '1555555555','full_mobile' => '8801555555555','account_no' => '45443193646446','pin_status' => '1','pin_code' => '1234','account_type' => 'personal','company_name' => NULL,'password' => '$2y$10$wd92fVlH1Plh6H5c9uqp9.MHiPr77AiuAIEa9RaEFHyxMHSVdKCgm','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh","state":"Dhaka","city":"Dhaka","zip":"1245","address":"Dhaka, Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '1','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-01-30 06:49:12','updated_at' => '2024-10-03 12:20:19'),
            array('id' => '2','firstname' => 'Test','lastname' => 'User','username' => 'testuser','email' => 'user2@appdevs.net','mobile_code' => '880','mobile' => '1333333333','full_mobile' => '8801333333333','account_no' => '11705450860582','pin_status' => '0','pin_code' => NULL,'account_type' => 'personal','company_name' => NULL,'password' => '$2y$10$jbOdCfYxGP4dMZb1IVOZCO6Tuv4KY551iBo1VqijtQmVhB4qgMYSe','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh","state":"Dhaka, Bangladesh","city":"Dhaka, Bangladesh","zip":"1245","address":"Dhaka, Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '1','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-01-31 09:16:06','updated_at' => '2024-02-07 02:19:29'),
            array('id' => '3','firstname' => 'Test','lastname' => 'User3','username' => 'testuser3','email' => 'user3@appdevs.net','mobile_code' => '880','mobile' => '177777777777','full_mobile' => '880177777777777','account_no' => '84744057242527','pin_status' => '0','pin_code' => NULL,'account_type' => 'personal','company_name' => NULL,'password' => '$2y$10$YfjAc.OsxexBiZ.It2PCRubi7cipu.3ib2nkQMo9wwjoOxvx4Ir.u','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh","state":"","city":"Dhaka","zip":"1245","address":"Dhaka, Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '1','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-01-31 10:55:04','updated_at' => '2024-10-03 09:31:54'),
            array('id' => '4','firstname' => 'App','lastname' => 'Devs','username' => 'appdevs2','email' => 'user4@appdevs.net','mobile_code' => NULL,'mobile' => NULL,'full_mobile' => NULL,'account_no' => '38633846709192','pin_status' => '0','pin_code' => NULL,'account_type' => 'business','company_name' => 'Rick Development','password' => '$2y$10$2WAAuLDOIfhlApkDtwqV6uohWMKI7C2ZnNytMu/dYkJ3oHFuKqRRK','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '0','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-10-03 10:08:04','updated_at' => '2024-10-03 10:08:04'),
            array('id' => '5','firstname' => 'App','lastname' => 'Devs3','username' => 'appdevs3','email' => 'user5@appdevs.net','mobile_code' => NULL,'mobile' => NULL,'full_mobile' => NULL,'account_no' => '37933750949739','pin_status' => '0','pin_code' => NULL,'account_type' => 'business','company_name' => 'Sixam','password' => '$2y$10$oHQFEDngERA7SBSItJLGJOvdLLzrb0z0TRpbaaWD6jprfYcWRnIbW','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '0','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-10-03 10:08:41','updated_at' => '2024-10-03 10:08:41'),
            array('id' => '6','firstname' => 'App','lastname' => 'Devs4','username' => 'appdevs4','email' => 'user6@appdevs.net','mobile_code' => NULL,'mobile' => NULL,'full_mobile' => NULL,'account_no' => '15108129326175','pin_status' => '0','pin_code' => NULL,'account_type' => 'business','company_name' => 'Dream71','password' => '$2y$10$OFKYn1OL8q96d4unE9U7jOmAHHHw2k6WxOKqd5qTeiiDVLYuibh7i','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '0','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-10-03 10:09:27','updated_at' => '2024-10-03 10:09:27'),
            array('id' => '7','firstname' => 'App','lastname' => 'Devs','username' => 'appdevs5','email' => 'user7@appdevs.net','mobile_code' => NULL,'mobile' => NULL,'full_mobile' => NULL,'account_no' => '21531340922758','pin_status' => '0','pin_code' => NULL,'account_type' => 'business','company_name' => 'NextVenture','password' => '$2y$10$AhhIsGNdQxqp2w8t3qRygeBeFxeekMZT4Lhjd2TjAAUYKHjh75XsC','birthdate' => NULL,'gender' => NULL,'referral_id' => NULL,'image' => NULL,'status' => '1','address' => '{"country":"Bangladesh"}','email_verified' => '1','sms_verified' => '1','kyc_verified' => '0','ver_code' => NULL,'ver_code_send_at' => NULL,'two_factor_verified' => '0','two_factor_status' => '0','two_factor_secret' => NULL,'email_verified_at' => NULL,'strowallet_customer' => NULL,'remember_token' => NULL,'deleted_at' => NULL,'created_at' => '2024-10-03 10:10:05','updated_at' => '2024-10-03 10:10:05')
        );
        
        User::insert($users);

        $user_wallets = array(
            array('id' => '1','user_id' => '1','currency_id' => '1','balance' => '1000.00000000','status' => '1','created_at' => NULL,'updated_at' => NULL),
            array('id' => '2','user_id' => '2','currency_id' => '1','balance' => '1000.00000000','status' => '1','created_at' => NULL,'updated_at' => NULL),
            array('id' => '3','user_id' => '3','currency_id' => '1','balance' => '1900.00000000','status' => '1','created_at' => NULL,'updated_at' => NULL),
            array('id' => '4','user_id' => '4','currency_id' => '1','balance' => '0.00000000','status' => '1','created_at' => '2024-10-03 10:08:04','updated_at' => NULL),
            array('id' => '5','user_id' => '5','currency_id' => '1','balance' => '0.00000000','status' => '1','created_at' => '2024-10-03 10:08:41','updated_at' => NULL),
            array('id' => '6','user_id' => '6','currency_id' => '1','balance' => '0.00000000','status' => '1','created_at' => '2024-10-03 10:09:27','updated_at' => NULL),
            array('id' => '7','user_id' => '7','currency_id' => '1','balance' => '1000000.00000000','status' => '1','created_at' => '2024-10-03 10:10:05','updated_at' => NULL)
        );

        UserWallet::insert($user_wallets);
    }
}
