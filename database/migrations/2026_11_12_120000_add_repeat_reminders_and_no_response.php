<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->timestamp('no_response_at')->nullable()->after('invited_at');
        });

        // Add the new unique key before dropping the old one: MySQL's foreign key needs one at all times.
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('invitation_id');
            $table->unique(['invitation_id', 'due_at']);
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropUnique(['invitation_id', 'interval_minutes']);
        });
    }

    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->unique(['invitation_id', 'interval_minutes']);
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropUnique(['invitation_id', 'due_at']);
            $table->dropColumn('due_at');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('no_response_at');
        });

        Schema::dropIfExists('app_settings');
    }
};
