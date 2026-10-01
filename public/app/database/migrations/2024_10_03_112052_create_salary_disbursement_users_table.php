<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salary_disbursement_users', function (Blueprint $table) {
            $table->id();
            $table->string('company_email');
            $table->string('company_name');
            $table->string('company_username');
            $table->string('user_name');
            $table->string('user_email');
            $table->string('user_username');
            $table->decimal('amount', 28, 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('salary_disbursement_users');
    }
};
