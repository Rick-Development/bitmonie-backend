<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\User\UserSeeder;
use Database\Seeders\Admin\RoleSeeder;
use Database\Seeders\Admin\AdminSeeder;
use Database\Seeders\Admin\CurrencySeeder;
use Database\Seeders\Admin\LanguageSeeder;
use Database\Seeders\Admin\SetupKycSeeder;
use Database\Seeders\Admin\SetupSeoSeeder;
use Database\Seeders\Admin\ExtensionSeeder;
use Database\Seeders\Admin\SetupPageSeeder;
use Database\Seeders\Admin\UsefulLinkSeeder;
use Database\Seeders\Admin\AppSettingsSeeder;
use Database\Seeders\Admin\AdminHasRoleSeeder;
use Database\Seeders\Admin\AnnouncementSeeder;
use Database\Seeders\Admin\SiteSectionsSeeder;
use Database\Seeders\Admin\BasicSettingsSeeder;
use Database\Seeders\Admin\PaymentGatewaySeeder;
use Database\Seeders\Admin\VirtualCardApiSeeder;
use Database\Seeders\Admin\AppOnBoardScreenSeeder;
use Database\Seeders\Admin\SystemMaintenanceSeeder;
use Database\Seeders\Admin\TransactionMethodSeeder;
use Database\Seeders\Admin\FreshBasicSettingsSeeder;
use Database\Seeders\Admin\TransactionSettingSeeder;
use Database\Seeders\Admin\FundTransferSettingsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        //fresh
        $this->call([
            AdminSeeder::class,
            RoleSeeder::class,
            TransactionSettingSeeder::class,
            CurrencySeeder::class,
            FreshBasicSettingsSeeder::class,
            SetupSeoSeeder::class,
            AppSettingsSeeder::class,
            SiteSectionsSeeder::class,
            AnnouncementSeeder::class,
            SetupKycSeeder::class,
            ExtensionSeeder::class,
            AdminHasRoleSeeder::class,
            SetupPageSeeder::class,
            LanguageSeeder::class,
            UsefulLinkSeeder::class,
            PaymentGatewaySeeder::class,
            TransactionMethodSeeder::class,
            FundTransferSettingsSeeder::class,
            VirtualCardApiSeeder::class,
            SystemMaintenanceSeeder::class,
            AppOnBoardScreenSeeder::class
        ]);

        //demo
        // $this->call([
        //     AdminSeeder::class,
        //     RoleSeeder::class,
        //     TransactionSettingSeeder::class,
        //     CurrencySeeder::class,
        //     BasicSettingsSeeder::class,
        //     SetupSeoSeeder::class,
        //     AppSettingsSeeder::class,
        //     SiteSectionsSeeder::class,
        //     AnnouncementSeeder::class,
        //     SetupKycSeeder::class,
        //     ExtensionSeeder::class,
        //     AdminHasRoleSeeder::class,
        //     SetupPageSeeder::class,
        //     LanguageSeeder::class,
        //     UsefulLinkSeeder::class,
        //     PaymentGatewaySeeder::class,
        //     TransactionMethodSeeder::class,
        //     FundTransferSettingsSeeder::class,
        //     VirtualCardApiSeeder::class,
        //     UserSeeder::class,
        //     SystemMaintenanceSeeder::class,
        //     AppOnBoardScreenSeeder::class
        // ]);
    }
}
