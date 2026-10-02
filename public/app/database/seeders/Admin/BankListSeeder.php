<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\BankList;
use Illuminate\Database\Seeder;

class BankListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fund_transfer_bank_lists = array(
            array('id' => '1','admin_id' => '1','name' => 'Sonali Bank Limited','alias' => 'sonali-bank-limited','status' => '1','created_at' => '2024-02-07 07:07:13','updated_at' => '2024-02-07 08:43:20'),
            array('id' => '2','admin_id' => '1','name' => 'Doutch Bangla Bank','alias' => 'doutch-bangla-bank','status' => '1','created_at' => '2024-02-07 07:07:26','updated_at' => '2024-02-07 07:07:26'),
            array('id' => '3','admin_id' => '1','name' => 'Brack Bank Limited','alias' => 'brack-bank-limited','status' => '1','created_at' => '2024-02-07 07:07:59','updated_at' => '2024-02-07 10:05:02'),
            array('id' => '4','admin_id' => '1','name' => 'Rupali Bank Limited','alias' => 'rupali-bank-limited','status' => '1','created_at' => '2024-02-07 07:08:33','updated_at' => '2024-02-07 10:05:22'),
            array('id' => '5','admin_id' => '1','name' => 'United Bank Limited','alias' => 'united-bank-limited','status' => '1','created_at' => '2024-02-07 07:08:52','updated_at' => '2024-02-07 07:08:52')
          );

        BankList::insert($fund_transfer_bank_lists);
    }
}
