<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vas_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vas_service_id')->constrained('vas_services')->onDelete('cascade');
            $table->string('name');
            $table->string('identifier'); // SafeHaven ID
            $table->boolean('status')->default(true);
            $table->timestamps();
            
            $table->unique(['vas_service_id', 'identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vas_service_categories');
    }
};
