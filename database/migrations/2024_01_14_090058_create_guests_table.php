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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->tinyInteger('guest_type');
            $table->bigInteger('division_id');
            $table->bigInteger('user_id');
            $table->tinyInteger('ticket_type');
            $table->string('selfie');
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default('0')->comment('0. pending, 1. approved, -1. rejected, 2. reschedule');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
