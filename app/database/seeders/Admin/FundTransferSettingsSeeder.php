<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\BankBranch;
use App\Models\Admin\BankList;
use App\Models\Admin\MobileBank;
use Illuminate\Database\Seeder;

class FundTransferSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $bank_lists = array(
            array('id' => '1','admin_id' => '1','name' => 'Sonali Bank Limited','alias' => 'sonali-bank-limited','status' => '0','created_at' => '2024-02-07 07:07:13','updated_at' => '2024-02-09 03:58:12'),
            array('id' => '2','admin_id' => '1','name' => 'Doutch Bangla Bank','alias' => 'doutch-bangla-bank','status' => '1','created_at' => '2024-02-07 07:07:26','updated_at' => '2024-02-07 07:07:26'),
            array('id' => '3','admin_id' => '1','name' => 'Rupali Bank Limited','alias' => 'rupali-bank-limited','status' => '1','created_at' => '2024-02-07 07:08:33','updated_at' => '2024-02-07 10:05:22'),
            array('id' => '4','admin_id' => '1','name' => 'United Bank Limited','alias' => 'united-bank-limited','status' => '1','created_at' => '2024-02-07 07:08:52','updated_at' => '2024-02-07 07:08:52'),
            array('id' => '5','admin_id' => '1','name' => 'Brack Bank Limited','alias' => 'brack-bank-limited','status' => '1','created_at' => '2024-02-08 04:00:32','updated_at' => '2024-02-08 04:00:32')
        );

        BankList::insert($bank_lists);

        $bank_branches = array(
            array('admin_id' => '1','bank_list_id' => '1','name' => 'Mirpur','alias' => 'mirpur','status' => '1','created_at' => '2024-02-07 11:03:57','updated_at' => '2024-02-07 11:03:57'),
            array('admin_id' => '1','bank_list_id' => '2','name' => 'Uttara','alias' => 'uttara','status' => '1','created_at' => '2024-02-07 11:27:01','updated_at' => '2024-02-07 11:27:01'),
            array('admin_id' => '1','bank_list_id' => '3','name' => 'Dhanmondi','alias' => 'dhanmondi','status' => '1','created_at' => '2024-02-07 11:39:17','updated_at' => '2024-02-07 12:19:05'),
            array('admin_id' => '1','bank_list_id' => '4','name' => 'Mirpur','alias' => 'mirpur','status' => '1','created_at' => '2024-02-08 04:02:38','updated_at' => '2024-02-08 04:02:38'),
            array('admin_id' => '1','bank_list_id' => '5','name' => 'Dhanmondi','alias' => 'dhanmondi','status' => '1','created_at' => '2024-02-08 04:02:47','updated_at' => '2024-02-08 04:02:47'),
        );

        BankBranch::insert($bank_branches);

        $mobile_banks = array(
            array('id' => '2','admin_id' => '1','name' => 'Bkash','alias' => 'bkash','status' => '1','created_at' => '2024-02-09 04:09:02','updated_at' => '2024-02-09 04:09:02'),
            array('id' => '3','admin_id' => '1','name' => 'Rocket','alias' => 'rocket','status' => '1','created_at' => '2024-02-09 04:09:21','updated_at' => '2024-02-09 04:09:21'),
            array('id' => '4','admin_id' => '1','name' => 'Nagad','alias' => 'nagad','status' => '1','created_at' => '2024-02-09 04:10:00','updated_at' => '2024-02-09 04:10:00')
        );

        MobileBank::insert($mobile_banks);
    }
}
