<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('teams')->updateOrInsert(
            ['slug' => 'seleccion-de-espana'],
            [
                'name' => 'Selección de España',
                'country_code' => 'ES',
                'sportsdb_id' => 133909,
                'sportsdb_query' => 'Spain',
                'sportsdb_region' => 'europa',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('teams')->where('slug', 'seleccion-de-espana')->delete();
    }
};
