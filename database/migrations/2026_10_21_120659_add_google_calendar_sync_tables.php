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
    Schema::create('google_connections', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->text('access_token');
        $table->text('refresh_token');
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('last_synced_at')->nullable();
        $table->text('last_error')->nullable();
        $table->timestamps();
    });

    Schema::table('meetings', function (Blueprint $table) {
        $table->timestamp('cancelled_at')->nullable()->after('google_event_id');
    });
}

public function down(): void
{
    Schema::table('meetings', function (Blueprint $table) {
        $table->dropColumn('cancelled_at');
    });

    Schema::dropIfExists('google_connections');
}
};
