<?php

use App\Models\PrecedenceRank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precedence_ranks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort')->index();
            $table->string('code', 10)->nullable();
            $table->string('title');
            $table->string('holder')->nullable();
            $table->string('aliases')->nullable();
            $table->timestamps();
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('precedence_rank_id')->nullable()->after('organization')->constrained()->nullOnDelete();
            $table->string('position_title')->nullable()->after('precedence_rank_id');
        });

        Schema::create('seating_plans', function (Blueprint $table) {
            $table->id();
            $table->string('context')->unique();
            $table->foreignId('meeting_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        PrecedenceRank::importRows(PrecedenceRank::rowsFromDataFile());
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_plans');

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('precedence_rank_id');
            $table->dropColumn('position_title');
        });

        Schema::dropIfExists('precedence_ranks');
    }
};
