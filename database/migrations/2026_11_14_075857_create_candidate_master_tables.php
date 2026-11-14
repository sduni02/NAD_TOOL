<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_master_records', function (Blueprint $table) {
            $table->id();
            $table->string('match_key')->nullable()->index(); // Roll / Reg / Student ID
            $table->longText('row_data'); // JSON map of all columns in 2nd file
            $table->timestamps();
        });

        Schema::create('candidate_master_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_master_records');
        Schema::dropIfExists('candidate_master_metadata');
    }
};
