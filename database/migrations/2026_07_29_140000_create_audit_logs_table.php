<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // nullOnDelete y no cascade: si se borra la cuenta, el rastro
            // de lo que hizo tiene que sobrevivir. Eso es la diferencia
            // entre un log y una nota adhesiva.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_label')->nullable();

            $table->string('action', 32);                 // created | updated | deleted
            $table->morphs('auditable');
            $table->string('auditable_label')->nullable();

            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_target_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
