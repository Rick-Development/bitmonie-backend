<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use App\Models\Admin\TransactionSetting;

class TransactionSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $transaction_settings = array(
            array('admin_id' => '1','slug' => 'own-bank-transfer','title' => 'Own Bank Transfer','fixed_charge' => '1.00','percent_charge' => '1.00','min_limit' => '0.00','max_limit' => '50000.00','monthly_limit' => '50000.00','daily_limit' => '5000.00','status' => '1','created_at' => now(),'updated_at' => NULL),
            array('admin_id' => '1','slug' => 'other-bank-transfer','title' => 'Other Bank Transfer','fixed_charge' => '1.00','percent_charge' => '1.00','min_limit' => '0.00','max_limit' => '50000.00','monthly_limit' => '50000.00','daily_limit' => '5000.00','status' => '1','created_at' => now(),'updated_at' => NULL),
            array('admin_id' => '1','slug' => 'virtual_card','title' => 'Virtual Card Charges','fixed_charge' => '1.00','percent_charge' => '1.00','min_limit' => '100.00','max_limit' => '50000.00','monthly_limit' => '50000.00','daily_limit' => '50000.00','status' => '1','created_at' => now(),'updated_at' => NULL),
            array('admin_id' => '1','slug' => 'reload_card','title' => 'Reload Card Charges','fixed_charge' => '1.00','percent_charge' => '1.00','min_limit' => '2.00','max_limit' => '50000.00','monthly_limit' => '50000.00','daily_limit' => '50000.00','status' => '1','created_at' => now(),'updated_at' => NULL),
        );
        TransactionSetting::upsert($transaction_settings,['slug'],[]);
    }
}
