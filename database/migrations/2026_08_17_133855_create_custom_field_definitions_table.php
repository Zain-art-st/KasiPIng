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
    Schema::create('custom_field_definitions', function (Blueprint $table) {
        $table->id();
        $table->string('field_key')->unique();
        $table->string('label');
        $table->unsignedTinyInteger('sort_order')->default(0);
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('custom_field_definitions');
}
};
