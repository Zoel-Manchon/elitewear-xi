<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Team;
use App\Models\TeamEquipment;
use App\Services\SportsData\CatalogSportsDbSync;
use App\Services\SportsData\SportsDbKitImageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ProductEquipmentController extends Controller
{
    public function sync(Request $request, Product $product, CatalogSportsDbSync $sync): RedirectResponse
    {
        $validated = $request->validate([
            'sportsdb_id' => ['nullable', 'integer', 'min:1'],
            'sportsdb_query' => ['nullable', 'string', 'max:160'],
        ]);

        /** @var Team $team */
        $team = $product->team()->firstOrFail();

        $team->update([
            'sportsdb_id' => $validated['sportsdb_id'] ?? null,
            'sportsdb_query' => trim((string) ($validated['sportsdb_query'] ?? '')) ?: $team->name,
        ]);

        try {
            $result = $sync->syncTeam($team);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'sportsdb' => 'No se pudo consultar TheSportsDB: '.$exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            "TheSportsDB actualizado: {$result['equipment']->count()} equipaciones, {$result['matched']} coincidencias.",
        );
    }

    public function use(
        Request $request,
        Product $product,
        TeamEquipment $equipment,
        SportsDbKitImageImporter $importer,
    ): RedirectResponse {
        abort_unless(
            $equipment->team_id === $product->team_id && $equipment->provider === 'thesportsdb',
            404,
        );

        $validated = $request->validate([
            'download' => ['nullable', 'boolean'],
            'replace_primary' => ['nullable', 'boolean'],
        ]);

        $product->update(['team_equipment_id' => $equipment->id]);

        if (! ($validated['download'] ?? false)) {
            return back()->with('status', 'Equipación vinculada al producto.');
        }

        try {
            $image = $importer->import(
                $product,
                $equipment,
                (bool) ($validated['replace_primary'] ?? false),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'sportsdb' => 'La equipación se vinculó, pero la imagen no pudo descargarse: '.$exception->getMessage(),
            ]);
        }

        return back()->with('status', "Equipación vinculada e imagen {$image->id} importada.");
    }
}
