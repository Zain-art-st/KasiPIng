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
    Schema::create('invitations', function (Blueprint $table) {
        $table->id();
        $table->string('vip_name');
        $table->string('organization')->nullable();
        $table->enum('attendance_status', ['pending', 'attending', 'not_attending'])->default('pending');
        $table->string('vehicle_registration')->nullable();
        $table->string('estimated_arrival')->nullable();
        $table->string('estimated_departure')->nullable();
        $table->string('submitted_by_name')->nullable();
        $table->string('submitted_by_email')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });
}
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
