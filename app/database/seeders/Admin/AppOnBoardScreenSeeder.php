<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\AppOnboardScreens;
use Illuminate\Database\Seeder;

class AppOnBoardScreenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $app_onboard_screens = array(
            array('heading' => '{"language":{"en":{"heading":"Fund Transfer"},"es":{"heading":"Transferencia de fondos"},"ar":{"heading":"\\u062a\\u062d\\u0648\\u064a\\u0644 \\u0627\\u0644\\u0623\\u0645\\u0648\\u0627\\u0644"}}}','title' => '{"language":{"en":{"title":"Send Money, Anytime, Anywhere"},"es":{"title":"Env\\u00eda dinero, en cualquier momento y a cualquier lugar"},"ar":{"title":"\\u0623\\u0631\\u0633\\u0644 \\u0627\\u0644\\u0623\\u0645\\u0648\\u0627\\u0644 \\u0641\\u064a \\u0623\\u064a \\u0648\\u0642\\u062a \\u0648\\u0641\\u064a \\u0623\\u064a \\u0645\\u0643\\u0627\\u0646"}}}','details' => '{"language":{"en":{"details":"Experience seamless and secure fund transfers across the globe with just a few taps."},"es":{"details":"Experimente transferencias de fondos fluidas y seguras en todo el mundo con solo unos pocos toques."},"ar":{"details":"\\u0627\\u0633\\u062a\\u0645\\u062a\\u0639 \\u0628\\u062a\\u062d\\u0648\\u064a\\u0644\\u0627\\u062a \\u0645\\u0627\\u0644\\u064a\\u0629 \\u0633\\u0644\\u0633\\u0629 \\u0648\\u0622\\u0645\\u0646\\u0629 \\u0625\\u0644\\u0649 \\u062c\\u0645\\u064a\\u0639 \\u0623\\u0646\\u062d\\u0627\\u0621 \\u0627\\u0644\\u0639\\u0627\\u0644\\u0645 \\u0628\\u0628\\u0636\\u0639 \\u0646\\u0642\\u0631\\u0627\\u062a \\u0641\\u0642\\u0637."}}}','image' => 'onboard1.webp','status' => '1','last_edit_by' => '1','created_at' => '2024-12-06 11:20:48','updated_at' => '2024-12-06 11:49:10'),
            array('heading' => '{"language":{"en":{"heading":"Virtual Card"},"es":{"heading":"Tarjeta virtual"},"ar":{"heading":"\\u0628\\u0637\\u0627\\u0642\\u0629 \\u0627\\u0641\\u062a\\u0631\\u0627\\u0636\\u064a\\u0629"}}}','title' => '{"language":{"en":{"title":"Your Card, Your Control"},"es":{"title":"Tu Tarjeta, Tu Control"},"ar":{"title":"\\u0628\\u0637\\u0627\\u0642\\u062a\\u0643 \\u0647\\u064a \\u0633\\u064a\\u0637\\u0631\\u062a\\u0643"}}}','details' => '{"language":{"en":{"details":"Enjoy secure online shopping and payments with a virtual card tailored to your needs."},"es":{"details":"Disfruta de compras y pagos online seguros con una tarjeta virtual adaptada a tus necesidades."},"ar":{"details":"\\u0627\\u0633\\u062a\\u0645\\u062a\\u0639 \\u0628\\u0627\\u0644\\u062a\\u0633\\u0648\\u0642 \\u0627\\u0644\\u0622\\u0645\\u0646 \\u0639\\u0628\\u0631 \\u0627\\u0644\\u0625\\u0646\\u062a\\u0631\\u0646\\u062a \\u0648\\u0627\\u0644\\u062f\\u0641\\u0639 \\u0628\\u0627\\u0633\\u062a\\u062e\\u062f\\u0627\\u0645 \\u0628\\u0637\\u0627\\u0642\\u0629 \\u0627\\u0641\\u062a\\u0631\\u0627\\u0636\\u064a\\u0629 \\u0645\\u0635\\u0645\\u0645\\u0629 \\u062e\\u0635\\u064a\\u0635\\u064b\\u0627 \\u0644\\u062a\\u0644\\u0628\\u064a\\u0629 \\u0627\\u062d\\u062a\\u064a\\u0627\\u062c\\u0627\\u062a\\u0643."}}}','image' => 'onboard2.webp','status' => '1','last_edit_by' => '1','created_at' => '2024-12-06 11:47:38','updated_at' => '2024-12-06 11:47:38'),
            array('heading' => '{"language":{"en":{"heading":"Setup Pin"},"es":{"heading":"Pin de configuraci\\u00f3n"},"ar":{"heading":"\\u0625\\u0639\\u062f\\u0627\\u062f \\u0627\\u0644\\u062f\\u0628\\u0648\\u0633"}}}','title' => '{"language":{"en":{"title":"Secure Your Transactions"},"es":{"title":"Proteja sus transacciones"},"ar":{"title":"\\u062a\\u0623\\u0645\\u064a\\u0646 \\u0645\\u0639\\u0627\\u0645\\u0644\\u0627\\u062a\\u0643"}}}','details' => '{"language":{"en":{"details":"Create a personal PIN to add an extra layer of security to your financial activities."},"es":{"details":"Cree un PIN personal para agregar una capa adicional de seguridad a sus actividades financieras."},"ar":{"details":"\\u0642\\u0645 \\u0628\\u0625\\u0646\\u0634\\u0627\\u0621 \\u0631\\u0642\\u0645 \\u062a\\u0639\\u0631\\u064a\\u0641 \\u0634\\u062e\\u0635\\u064a (PIN) \\u0644\\u0625\\u0636\\u0627\\u0641\\u0629 \\u0637\\u0628\\u0642\\u0629 \\u0625\\u0636\\u0627\\u0641\\u064a\\u0629 \\u0645\\u0646 \\u0627\\u0644\\u0623\\u0645\\u0627\\u0646 \\u0625\\u0644\\u0649 \\u0623\\u0646\\u0634\\u0637\\u062a\\u0643 \\u0627\\u0644\\u0645\\u0627\\u0644\\u064a\\u0629."}}}','image' => 'onboard3.webp','status' => '1','last_edit_by' => '1','created_at' => '2024-12-06 12:01:44','updated_at' => '2024-12-06 12:01:44')
          );

        AppOnboardScreens::insert($app_onboard_screens);
    }
}
