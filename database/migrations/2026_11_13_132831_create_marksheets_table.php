<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marksheets', function (Blueprint $table) {
            $table->id();
            $table->string('id_marksheet')->nullable()->index();
            $table->string('userid')->nullable();
            $table->string('student_id')->nullable()->index();
            $table->string('admission_no')->nullable()->index();
            $table->string('a_course_code')->nullable();
            $table->string('subject_code')->nullable()->index();
            $table->string('semester')->nullable();
            $table->string('subject_credit')->nullable();
            $table->string('total_marks')->nullable();
            $table->string('external_marks')->nullable();
            $table->string('internal_marks')->nullable();
            $table->string('pass_marks')->nullable();
            $table->string('external_marks_obtained')->nullable();
            $table->string('internal_marks_obtained')->nullable();
            $table->string('marks_obtained')->nullable();
            $table->string('grade_obtained')->nullable();
            $table->string('grade_point')->nullable();
            $table->string('credit_point')->nullable();
            $table->string('exam_session')->nullable();
            $table->string('record_created_at')->nullable();
            $table->string('record_updated_at')->nullable();
            $table->string('reg')->nullable()->index();
            $table->string('roll')->nullable()->index();
            $table->string('cname')->nullable()->index();
            $table->string('date_of_birth')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marksheets');
    }
};
