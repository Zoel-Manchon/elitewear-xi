<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'team' => $this->whenLoaded('team', fn () => [
                'slug' => $this->team->slug,
                'name' => $this->team->name,
                'sportsdb_id' => $this->team->sportsdb_id,
                'sportsdb_name' => $this->team->sportsdb_name,
                'short_name' => $this->team->sportsdb_short_name,
                'alternate_name' => $this->team->sportsdb_alternate_name,
                'country_code' => $this->team->country_code,
                'country' => $this->team->sportsdb_country,
                'region' => $this->team->sportsdb_region,
                'formed_year' => $this->team->sportsdb_formed_year,
                'sport' => $this->team->sportsdb_sport,
                'league' => [
                    'id' => $this->team->sportsdb_league_id,
                    'name' => $this->team->sportsdb_league,
                ],
                'stadium' => [
                    'name' => $this->team->sportsdb_stadium,
                    'capacity' => $this->team->sportsdb_stadium_capacity,
                    'location' => $this->team->sportsdb_location,
                ],
                'keywords' => $this->team->sportsdb_keywords,
                'description' => $this->team->sportsDbDescription(),
                'website' => $this->team->sportsDbWebsiteUrl(),
                'colours' => array_values(array_filter([
                    $this->team->sportsdb_colour_1,
                    $this->team->sportsdb_colour_2,
                    $this->team->sportsdb_colour_3,
                ])),
                'media' => [
                    'badge' => $this->team->sportsdb_badge_url,
                    'logo' => $this->team->sportsdb_logo_url,
                    'banner' => $this->team->sportsdb_banner_url,
                    'fanart' => $this->team->sportsdb_fanart_url,
                ],
            ]),
            'season' => $this->season,
            'kit_type' => $this->kit_type->value,
            'kit_type_label' => $this->kit_type->label(),
            'equipment_source' => $this->whenLoaded('teamEquipment', function () {
                if (! $this->teamEquipment) {
                    return null;
                }

                $payload = $this->teamEquipment->raw_payload ?? [];

                return [
                    'provider' => $this->teamEquipment->provider,
                    'idEquipment' => $this->teamEquipment->external_equipment_id,
                    'idTeam' => $this->teamEquipment->external_team_id,
                    'date' => $this->teamEquipment->source_created_at?->toIso8601String(),
                    'strSeason' => $this->teamEquipment->season,
                    'strEquipment' => $this->teamEquipment->image_url,
                    'strType' => $this->teamEquipment->equipment_type,
                    'strUsername' => $this->teamEquipment->contributor,
                    'from_team_profile' => (bool) ($payload['_profile_equipment'] ?? false),
                    'source_endpoint' => $payload['_source_endpoint'] ?? 'lookupequipment.php',
                ];
            }),
            'description' => $this->description,
            'price' => [
                'amount_cents' => $this->base_price_cents,
                'currency' => $this->currency,
                'formatted' => $this->basePrice()->format(),
            ],
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images' => $this->whenLoaded('images', function () {
                if ($this->images->isEmpty()) {
                    return [[
                        'url' => asset('images/placeholder.svg'),
                        'alt' => $this->name,
                        'placeholder' => true,
                    ]];
                }

                return $this->images->map(fn ($image) => [
                    'url' => $image->url(),
                    'alt' => $image->alt,
                    'placeholder' => false,
                    'source' => [
                        'provider' => $image->source_provider,
                        'external_id' => $image->source_external_id,
                        'url' => $image->source_url,
                        'credit' => $image->source_credit,
                        'downloaded_at' => $image->downloaded_at?->toIso8601String(),
                    ],
                ]);
            }),
            'url' => route('products.show', $this->resource),
        ];
    }
}
