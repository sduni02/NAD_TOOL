<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_records', function (Blueprint $table) {
            $table->id();
            $table->string('student_key')->nullable()->index();
            $table->longText('row_data'); // JSON encoded row key-value map
            $table->timestamps();
        });

        Schema::create('reference_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable(); // JSON or text value
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_records');
        Schema::dropIfExists('reference_metadata');
    }
};
