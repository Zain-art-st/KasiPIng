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
    Schema::table('invitations', function (Blueprint $table) {
        $table->string('vip_email')->nullable()->after('organization');
        $table->string('vip_phone')->nullable()->after('vip_email');
    });
}

public function down(): void
{
    Schema::table('invitations', function (Blueprint $table) {
        $table->dropColumn(['vip_email', 'vip_phone']);
    });
}
};
