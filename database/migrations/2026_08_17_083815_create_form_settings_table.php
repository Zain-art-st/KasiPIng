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
    Schema::create('form_settings', function (Blueprint $table) {
        $table->id();
        $table->string('page_heading')->default('Independence Day Attendance Confirmation');
        $table->text('intro_text')->nullable();
        $table->string('attendance_question')->default('Will you be attending?');
        $table->string('attending_label')->default('Yes, attending');
        $table->string('not_attending_label')->default('No, not attending');
        $table->string('vehicle_label')->default('Vehicle registration number');
        $table->string('arrival_label')->default('Estimated arrival time');
        $table->string('departure_label')->default('Estimated departure time');
        $table->string('name_label')->default('Your name');
        $table->string('submit_button_label')->default('Submit');
        $table->string('thank_you_message')->default('Thank you — your response has been recorded.');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_settings');
    }
};
