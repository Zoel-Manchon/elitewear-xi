<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const QUERIES = [
        'AFC Ajax' => 'Ajax',
        'Real Madrid CF' => 'Real Madrid',
        'FC Barcelona' => 'Barcelona',
        'Juventus FC' => 'Juventus',
        'AC Milan' => 'AC Milan',
        'Olympique de Marseille' => 'Marseille',
        'Manchester United' => 'Manchester United',
        'Liverpool FC' => 'Liverpool',
        'FC Bayern München' => 'Bayern Munich',
        'FC Porto' => 'FC Porto',
        'Celtic FC' => 'Celtic',
        'Crvena Zvezda' => 'Red Star Belgrade',
        'Boca Juniors' => 'Boca Juniors',
        'Club Atlético River Plate' => 'River Plate',
        'Clube de Regatas do Flamengo' => 'Flamengo',
        'Sociedade Esportiva Palmeiras' => 'Palmeiras',
        'Club Atlético Peñarol' => 'Penarol',
        'Colo-Colo' => 'Colo-Colo',
        'Club América' => 'Club America',
        'Atlético Nacional' => 'Atletico Nacional',
        'Kashima Antlers' => 'Kashima Antlers',
        'Urawa Red Diamonds' => 'Urawa Red Diamonds',
        'Al-Hilal SFC' => 'Al-Hilal Saudi FC',
        'Persepolis FC' => 'Persepolis',
        'Al Ahly SC' => 'Al Ahly',
        'Raja Club Athletic' => 'Raja Casablanca',
        'TP Mazembe' => 'TP Mazembe',
        'Selección de Países Bajos' => 'Netherlands',
        'Selección de Alemania' => 'Germany',
        'Selección de Italia' => 'Italy',
        'Selección de Dinamarca' => 'Denmark',
        'Selección de Croacia' => 'Croatia',
        'Selección de Brasil' => 'Brazil',
        'Selección de Argentina' => 'Argentina',
        'Selección de México' => 'Mexico',
        'Selección de Colombia' => 'Colombia',
        'Selección de Japón' => 'Japan',
        'Selección de Corea del Sur' => 'South Korea',
        'Selección de Arabia Saudí' => 'Saudi Arabia',
        'Selección de Nigeria' => 'Nigeria',
        'Selección de Camerún' => 'Cameroon',
        'Selección de Sudáfrica' => 'South Africa',
        'Selección de Marruecos' => 'Morocco',
    ];

    public function up(): void
    {
        foreach (self::QUERIES as $name => $query) {
            DB::table('teams')
                ->where('name', $name)
                ->whereNull('sportsdb_query')
                ->update(['sportsdb_query' => $query]);
        }
    }

    public function down(): void
    {
        foreach (self::QUERIES as $name => $query) {
            DB::table('teams')
                ->where('name', $name)
                ->where('sportsdb_query', $query)
                ->update(['sportsdb_query' => null]);
        }
    }
};
