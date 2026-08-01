<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Catálogo.
     *
     * Este listado solo define los equipos disponibles. Los productos visibles
     * se reconstruyen después desde lookupequipment.php para que temporada,
     * tipo e imagen coincidan exactamente con TheSportsDB.
     *
     * [equipo, país, temporada, patrón, color1, color2, dorsal, precio, ámbito, región]
     */
    private const CATALOG = [
        // --- Europa: clubes ---------------------------------------------------
        ['AFC Ajax', 'NL', '1994-95', 'stripes', '#f4f1e8', '#d0021b', 10, 8995, 'clubes', 'europa'],
        ['Real Madrid CF', 'ES', '1997-98', 'solid', '#f4f1e8', '#3b2c6e', 7, 9495, 'clubes', 'europa'],
        ['FC Barcelona', 'ES', '1992-93', 'stripes', '#a50044', '#004d98', 9, 9295, 'clubes', 'europa'],
        ['Juventus FC', 'IT', '1995-96', 'stripes', '#f4f1e8', '#141414', 10, 8795, 'clubes', 'europa'],
        ['AC Milan', 'IT', '1989-90', 'stripes', '#c8102e', '#141414', 3, 9695, 'clubes', 'europa'],
        ['Olympique de Marseille', 'FR', '1992-93', 'solid', '#f4f1e8', '#2faee0', 6, 8995, 'clubes', 'europa'],
        ['Manchester United', 'GB', '1998-99', 'solid', '#da291c', '#f4f1e8', 7, 9895, 'clubes', 'europa'],
        ['Liverpool FC', 'GB', '1985-86', 'solid', '#c8102e', '#f4f1e8', 7, 9195, 'clubes', 'europa'],
        ['FC Bayern München', 'DE', '2000-01', 'solid', '#dc052d', '#f4f1e8', 10, 8495, 'clubes', 'europa'],
        ['FC Porto', 'PT', '1986-87', 'stripes', '#f4f1e8', '#0d4c92', 9, 8995, 'clubes', 'europa'],
        ['Celtic FC', 'GB', '1988-89', 'hoops', '#f4f1e8', '#018749', 8, 8695, 'clubes', 'europa'],
        ['Crvena Zvezda', 'RS', '1990-91', 'stripes', '#f4f1e8', '#d0021b', 11, 9395, 'clubes', 'europa'],

        // --- América Latina: clubes ------------------------------------------
        ['Boca Juniors', 'AR', '1981', 'hoops', '#0a3d91', '#f9c22e', 10, 9595, 'clubes', 'america'],
        ['Club Atlético River Plate', 'AR', '1986', 'sash', '#f4f1e8', '#d0021b', 10, 9295, 'clubes', 'america'],
        ['Clube de Regatas do Flamengo', 'BR', '1981', 'hoops', '#d0021b', '#141414', 10, 9795, 'clubes', 'america'],
        ['Sociedade Esportiva Palmeiras', 'BR', '1993', 'solid', '#006437', '#f4f1e8', 9, 8395, 'clubes', 'america'],
        ['Club Atlético Peñarol', 'UY', '1987', 'stripes', '#141414', '#f9c22e', 7, 8895, 'clubes', 'america'],
        ['Colo-Colo', 'CL', '1991', 'solid', '#f4f1e8', '#141414', 10, 8595, 'clubes', 'america'],
        ['Club América', 'MX', '1988-89', 'solid', '#f9c22e', '#0a3d91', 11, 8195, 'clubes', 'america'],
        ['Atlético Nacional', 'CO', '1989', 'stripes', '#0a7a3e', '#f4f1e8', 5, 8695, 'clubes', 'america'],

        // --- Asia: clubes -----------------------------------------------------
        ['Kashima Antlers', 'JP', '1996', 'stripes', '#a5192e', '#0a3d91', 8, 8295, 'clubes', 'asia'],
        ['Urawa Red Diamonds', 'JP', '1993', 'solid', '#c8102e', '#141414', 9, 8095, 'clubes', 'asia'],
        ['Al-Hilal SFC', 'SA', '1992', 'solid', '#0a3d91', '#f4f1e8', 10, 7995, 'clubes', 'asia'],
        ['Persepolis FC', 'IR', '1997', 'solid', '#c8102e', '#f4f1e8', 7, 7895, 'clubes', 'asia'],

        // --- África: clubes ---------------------------------------------------
        ['Al Ahly SC', 'EG', '1987', 'solid', '#c8102e', '#f4f1e8', 10, 8195, 'clubes', 'africa'],
        ['Raja Club Athletic', 'MA', '1997', 'solid', '#0a7a3e', '#f4f1e8', 11, 8095, 'clubes', 'africa'],
        ['TP Mazembe', 'CD', '2000', 'solid', '#141414', '#f4f1e8', 9, 7995, 'clubes', 'africa'],

        // --- Selecciones: Europa ---------------------------------------------
        ['Selección de Países Bajos', 'NL', '1988', 'solid', '#ec6b1f', '#f4f1e8', 12, 10995, 'selecciones', 'europa'],
        ['Selección de Alemania', 'DE', '1990', 'solid', '#f4f1e8', '#141414', 10, 10495, 'selecciones', 'europa'],
        ['Selección de Italia', 'IT', '1994', 'solid', '#1560bd', '#f4f1e8', 10, 10295, 'selecciones', 'europa'],
        ['Selección de Dinamarca', 'DK', '1986', 'halves', '#c8102e', '#f4f1e8', 11, 11495, 'selecciones', 'europa'],
        ['Selección de Croacia', 'HR', '1998', 'quarters', '#f4f1e8', '#d0021b', 10, 10795, 'selecciones', 'europa'],

        // --- Selecciones: América --------------------------------------------
        ['Selección de Brasil', 'BR', '1982', 'solid', '#f9d616', '#0a6b3d', 10, 11295, 'selecciones', 'america'],
        ['Selección de Argentina', 'AR', '1986', 'stripes', '#6cace4', '#f4f1e8', 10, 11995, 'selecciones', 'america'],
        ['Selección de México', 'MX', '1998', 'solid', '#006341', '#f4f1e8', 11, 9295, 'selecciones', 'america'],
        ['Selección de Colombia', 'CO', '1990', 'solid', '#f9d616', '#0a3d91', 10, 9695, 'selecciones', 'america'],

        // --- Selecciones: Asia -------------------------------------------------
        ['Selección de Japón', 'JP', '1998', 'solid', '#0a3d91', '#f4f1e8', 8, 8995, 'selecciones', 'asia'],
        ['Selección de Corea del Sur', 'KR', '2002', 'solid', '#c8102e', '#f4f1e8', 7, 8795, 'selecciones', 'asia'],
        ['Selección de Arabia Saudí', 'SA', '1994', 'solid', '#f4f1e8', '#0a7a3e', 10, 8495, 'selecciones', 'asia'],

        // --- Selecciones: África -----------------------------------------------
        ['Selección de Nigeria', 'NG', '1994', 'solid', '#0a8f4d', '#f4f1e8', 10, 8495, 'selecciones', 'africa'],
        ['Selección de Camerún', 'CM', '1990', 'solid', '#0a7a3e', '#d0021b', 9, 9895, 'selecciones', 'africa'],
        ['Selección de Sudáfrica', 'ZA', '1996', 'solid', '#f9d616', '#0a7a3e', 11, 8595, 'selecciones', 'africa'],
        ['Selección de Marruecos', 'MA', '1986', 'solid', '#c8102e', '#0a7a3e', 10, 9195, 'selecciones', 'africa'],
    ];

    /** Nombre de búsqueda compatible con TheSportsDB para cada equipo local. */
    private const SPORTSDB_QUERIES = [
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

    private const REGIONS = [
        'europa' => 'Europa',
        'america' => 'América',
        'asia' => 'Asia',
        'africa' => 'África',
    ];

    private const SCOPES = [
        'clubes' => 'Clubes',
        'selecciones' => 'Selecciones',
    ];

    public function run(): void
    {
        collect(self::SCOPES + self::REGIONS + [
            'anos-70' => 'Años 70',
            'anos-80' => 'Años 80',
            'anos-90' => 'Años 90',
            'anos-2000' => 'Años 2000',
            'anos-2010' => 'Años 2010',
            'anos-2020' => 'Años 2020',
        ])->each(fn (string $name, string $slug) => Category::firstOrCreate(['slug' => $slug], ['name' => $name])
        );

        /** @var array<string, int> $sportsDbIds */
        $sportsDbIds = config('sportsdb_catalog.teams', []);

        foreach (self::CATALOG as $entry) {
            $teamName = $entry[0];
            $country = $entry[1];
            $slug = Str::slug($teamName);

            Team::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $teamName,
                    'country_code' => $country,
                    'sportsdb_id' => $sportsDbIds[$slug] ?? null,
                    'sportsdb_query' => self::SPORTSDB_QUERIES[$teamName] ?? $teamName,
                ],
            );
        }

        $this->command?->info(
            'Equipos creados. Ejecuta catalog:sportsdb-full --force para ampliar y reconstruir el catálogo.'
        );
    }
}
