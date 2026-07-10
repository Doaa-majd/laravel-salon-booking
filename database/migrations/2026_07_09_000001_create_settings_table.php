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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('salon_name', 150);
            $table->string('logo', 255)->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->enum('currency', ['SAR', 'AED'])->default('SAR');
            $table->string('timezone', 50)->default('Asia/Riyadh');
            $table->tinyInteger('week_start')->default(0);
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->json('working_hours');
            $table->text('reminder_template')->nullable();
            $table->string('license_domain', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
