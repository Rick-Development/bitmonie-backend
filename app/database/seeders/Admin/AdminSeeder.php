<?php

namespace Database\Seeders\Admin;

use Illuminate\Database\Seeder;
use App\Models\Admin\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'firstname'     => "Super",
                'lastname'      => "Admin",
                'username'      => "superadmin",
                'email'         => "superadmin@appdevs.net",
                'password'      => Hash::make("appdevs"),
                'created_at'    => now(),
                'status'        => true,
            ],
            [
                'firstname'     => "Sub",
                'lastname'      => "Admin",
                'username'      => "subadmin",
                'email'         => "subadmin@appdevs.net",
                'password'      => Hash::make("appdevs"),
                'created_at'    => now(),
                'status'        => true,
            ],
            [
                'firstname'     => "Support",
                'lastname'      => "Admin",
                'username'      => "supportadmin",
                'email'         => "supportadmin@appdevs.net",
                'password'      => Hash::make("appdevs"),
                'created_at'    => now(),
                'status'        => true,
            ],
            [
                'firstname'     => "Editor",
                'lastname'      => "Admin",
                'username'      => "editoradmin",
                'email'         => "editoradmin@appdevs.net",
                'password'      => Hash::make("appdevs"),
                'created_at'    => now(),
                'status'        => true,
            ],
            [
                'firstname'     => "Moderator",
                'lastname'      => "Admin",
                'username'      => "moderatoradmin",
                'email'         => "moderatoradmin@appdevs.net",
                'password'      => Hash::make("appdevs"),
                'created_at'    => now(),
                'status'        => true,
            ],
        ];

        Admin::insert($data);
    }
}
