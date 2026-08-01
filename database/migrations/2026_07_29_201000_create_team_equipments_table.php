<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('thesportsdb');

            // Los siete campos publicados por lookupequipment.php.
            $table->string('external_equipment_id', 32);
            $table->string('external_team_id', 32);
            $table->timestamp('source_created_at')->nullable();
            $table->string('season', 20)->nullable();
            $table->text('image_url');
            $table->string('equipment_type', 32)->nullable();
            $table->string('contributor', 100)->nullable();

            // Conserva la respuesta completa por si el proveedor amplía el esquema.
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_equipment_id']);
            $table->index(['team_id', 'season', 'equipment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_equipments');
    }
};
