<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('sportsdb_short_name', 32)->nullable()->after('sportsdb_name');
            $table->text('sportsdb_alternate_name')->nullable()->after('sportsdb_short_name');
            $table->unsignedSmallInteger('sportsdb_formed_year')->nullable()->after('sportsdb_alternate_name');
            $table->string('sportsdb_sport', 50)->nullable()->after('sportsdb_formed_year');
            $table->string('sportsdb_league')->nullable()->after('sportsdb_sport');
            $table->string('sportsdb_league_id', 32)->nullable()->after('sportsdb_league');
            $table->string('sportsdb_country', 100)->nullable()->after('sportsdb_league_id');
            $table->string('sportsdb_region', 32)->nullable()->after('sportsdb_country');
            $table->string('sportsdb_stadium')->nullable()->after('sportsdb_region');
            $table->unsignedInteger('sportsdb_stadium_capacity')->nullable()->after('sportsdb_stadium');
            $table->string('sportsdb_location')->nullable()->after('sportsdb_stadium_capacity');
            $table->text('sportsdb_keywords')->nullable()->after('sportsdb_location');
            $table->text('sportsdb_website')->nullable()->after('sportsdb_keywords');
            $table->longText('sportsdb_description_es')->nullable()->after('sportsdb_website');
            $table->longText('sportsdb_description_en')->nullable()->after('sportsdb_description_es');
            $table->text('sportsdb_logo_url')->nullable()->after('sportsdb_badge_url');
            $table->text('sportsdb_banner_url')->nullable()->after('sportsdb_logo_url');
            $table->text('sportsdb_fanart_url')->nullable()->after('sportsdb_banner_url');
            $table->string('sportsdb_colour_1', 16)->nullable()->after('sportsdb_fanart_url');
            $table->string('sportsdb_colour_2', 16)->nullable()->after('sportsdb_colour_1');
            $table->string('sportsdb_colour_3', 16)->nullable()->after('sportsdb_colour_2');
            $table->json('sportsdb_profile_payload')->nullable()->after('sportsdb_equipment_url');

            $table->index(['sportsdb_country', 'sportsdb_league']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['sportsdb_country', 'sportsdb_league']);
            $table->dropColumn([
                'sportsdb_short_name',
                'sportsdb_alternate_name',
                'sportsdb_formed_year',
                'sportsdb_sport',
                'sportsdb_league',
                'sportsdb_league_id',
                'sportsdb_country',
                'sportsdb_region',
                'sportsdb_stadium',
                'sportsdb_stadium_capacity',
                'sportsdb_location',
                'sportsdb_keywords',
                'sportsdb_website',
                'sportsdb_description_es',
                'sportsdb_description_en',
                'sportsdb_logo_url',
                'sportsdb_banner_url',
                'sportsdb_fanart_url',
                'sportsdb_colour_1',
                'sportsdb_colour_2',
                'sportsdb_colour_3',
                'sportsdb_profile_payload',
            ]);
        });
    }
};
