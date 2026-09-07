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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('position')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('guest_type')->nullable();
            $table->text('survey_questions')->nullable();
            $table->text('survey_answers')->nullable();
            $table->text('review')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
