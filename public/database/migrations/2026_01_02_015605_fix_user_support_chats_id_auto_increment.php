<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fix the id column to have AUTO_INCREMENT
        DB::statement('ALTER TABLE user_support_chats MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert back (though this is not recommended in production)
        DB::statement('ALTER TABLE user_support_chats MODIFY id BIGINT UNSIGNED NOT NULL');
    }
};
