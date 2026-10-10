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
        $table->string('representative_name')->nullable()->after('attendance_status');
        $table->string('representative_role')->nullable()->after('representative_name');
        $table->boolean('is_locked')->default(false)->after('submitted_at');
    });

    // Widen the enum to include the new status
    DB::statement("ALTER TABLE invitations MODIFY attendance_status ENUM('pending', 'attending', 'representative_attending', 'not_attending') DEFAULT 'pending'");
}

public function down(): void
{
    Schema::table('invitations', function (Blueprint $table) {
        $table->dropColumn(['representative_name', 'representative_role', 'is_locked']);
    });

    DB::statement("ALTER TABLE invitations MODIFY attendance_status ENUM('pending', 'attending', 'not_attending') DEFAULT 'pending'");
}
};
