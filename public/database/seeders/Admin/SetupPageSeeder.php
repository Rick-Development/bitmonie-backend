<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\SetupPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SetupPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $pages              =  [
            "Home"          => "/",
            "About"         => "/about",
            'Services'      => "/services",
            "Web Journals"  => "/web-journals",
            "Contact"       => "/contact"
        ];
        
        $data = [];
        foreach($pages as $item => $url) {
            $data[] = [
                'slug'          => Str::slug($item),
                'title'         => $item,
                'url'           => $url,
                'last_edit_by'  => 1,
                'created_at'    => now(),
            ];
        }   

        SetupPage::insert($data);
    }
}
