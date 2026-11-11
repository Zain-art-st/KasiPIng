<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_settings', function (Blueprint $table) {
            $table->string('representative_label')->default('Sending a representative')->after('attending_label');
        });

        Schema::table('invitations', function (Blueprint $table) {
            // When the invitation email was last sent, so the admin can see who has not been invited yet.
            $table->timestamp('invited_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });

        Schema::table('form_settings', function (Blueprint $table) {
            $table->dropColumn('representative_label');
        });
    }
};
