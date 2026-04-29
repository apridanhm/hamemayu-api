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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Sejarah", "Budaya", dll
            $table->string('slug')->unique(); // "sejarah", "budaya"
            $table->enum('type', ['pilar', 'destination', 'tech', 'culture'])->default('pilar');
            $table->integer('order')->default(0); // untuk sorting di frontend
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
