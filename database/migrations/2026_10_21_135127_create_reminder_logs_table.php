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
    Schema::create('reminder_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
        $table->unsignedSmallInteger('interval_minutes');
        $table->string('status')->default('queued');
        $table->text('detail')->nullable();
        $table->timestamps();

        $table->unique(['invitation_id', 'interval_minutes']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
