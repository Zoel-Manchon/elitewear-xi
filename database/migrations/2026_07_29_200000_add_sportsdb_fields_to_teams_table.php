<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('sportsdb_id')->nullable()->unique()->after('crest_path');
            $table->string('sportsdb_query')->nullable()->after('sportsdb_id');
            $table->string('sportsdb_name')->nullable()->after('sportsdb_query');
            $table->text('sportsdb_badge_url')->nullable()->after('sportsdb_name');
            $table->text('sportsdb_equipment_url')->nullable()->after('sportsdb_badge_url');
            $table->timestamp('sportsdb_synced_at')->nullable()->after('sportsdb_equipment_url');
            $table->text('sportsdb_sync_error')->nullable()->after('sportsdb_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['sportsdb_id']);
            $table->dropColumn([
                'sportsdb_id',
                'sportsdb_query',
                'sportsdb_name',
                'sportsdb_badge_url',
                'sportsdb_equipment_url',
                'sportsdb_synced_at',
                'sportsdb_sync_error',
            ]);
        });
    }
};
