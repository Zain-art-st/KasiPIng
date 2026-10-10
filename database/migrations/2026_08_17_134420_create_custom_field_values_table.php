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
    Schema::create('custom_field_values', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
        $table->string('field_key');
        $table->string('value')->nullable();
        $table->timestamps();

        $table->unique(['invitation_id', 'field_key']);
    });
}

public function down(): void
{
    Schema::dropIfExists('custom_field_values');
}
};
