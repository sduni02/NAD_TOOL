<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('template_headers'); // JSON array of header column strings
            $table->text('mapping_config');    // JSON object of column => mapping rules
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_presets');
    }
};
