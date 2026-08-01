<?php

namespace App\Services\SportsData;

use App\Enums\KitType;
use App\Models\Product;
use App\Models\TeamEquipment;
use Illuminate\Support\Collection;

final class EquipmentMatcher
{
    /** @param Collection<int, TeamEquipment> $equipment */
    public function bestFor(Product $product, Collection $equipment): ?TeamEquipment
    {
        $season = $this->normalizeSeason($product->season);
        $rawKitType = $product->getAttribute('kit_type');
        $kitType = $rawKitType instanceof KitType
            ? $rawKitType
            : KitType::tryFrom((string) $rawKitType);

        if ($kitType === null) {
            return null;
        }

        return $equipment
            ->filter(fn (TeamEquipment $item): bool => $this->normalizeSeason($item->season) === $season)
            ->sortByDesc(function (TeamEquipment $item) use ($kitType): int {
                if ($item->kitType() === $kitType) {
                    return 100;
                }

                // Una camiseta sin tipo puede proponerse, pero nunca gana a una coincidencia exacta.
                return $item->kitType() === null ? 10 : 0;
            })
            ->first(fn (TeamEquipment $item): bool => $item->kitType() === $kitType || $item->kitType() === null);
    }

    public function normalizeSeason(?string $season): ?string
    {
        $season = trim((string) $season);

        if ($season === '') {
            return null;
        }

        $season = str_replace(['/', '_', '–', '—'], '-', $season);

        if (preg_match('/^(\d{4})-(\d{2})$/', $season, $matches) === 1) {
            $century = substr($matches[1], 0, 2);
            $end = (int) $matches[2];
            $start = (int) substr($matches[1], 2, 2);

            if ($end < $start) {
                $century = (string) ((int) $century + 1);
            }

            return "{$matches[1]}-{$century}".str_pad((string) $end, 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^(\d{4})-(\d{4})$/', $season, $matches) === 1) {
            return "{$matches[1]}-{$matches[2]}";
        }

        if (preg_match('/^\d{4}$/', $season) === 1) {
            return $season;
        }

        return strtolower(preg_replace('/\s+/', '', $season) ?? $season);
    }
}
