<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\VirtualCardApi;
use Illuminate\Database\Seeder;

class VirtualCardApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $virtual_card_apis = array(
            array('admin_id' => '1','image' => 'seeder/virtual-card.png','card_details' => 'This card is property of iBanking, Wonderland. Misuse is criminal offence. If found, please return to iBanking or to the nearest bank.','card_limit' => '3','config' => '{"strowallet_public_key":"R67MNEPQV2ABQW9HDD7JQFXQ2AJMMY","strowallet_secret_key":"AOC963E385FORPRRCXQJ698C1Q953B","strowallet_url":"https:\\/\\/strowallet.com\\/api\\/bitvcard\\/","strowallet_mode":"sandbox","name":"strowallet"}','created_at' => '2024-08-22 11:58:03','updated_at' => '2024-09-11 13:29:27')
        );
        VirtualCardApi::insert($virtual_card_apis);
    }
}
