<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#1b4d3e')->after('kit_type');
            $table->string('secondary_color', 7)->default('#f4f1e8')->after('primary_color');
            $table->string('pattern', 16)->default('solid')->after('secondary_color');
            $table->unsignedTinyInteger('shirt_number')->nullable()->after('pattern');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'pattern', 'shirt_number']);
        });
    }
};
