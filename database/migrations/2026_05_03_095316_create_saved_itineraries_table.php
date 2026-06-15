<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_itineraries', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke user
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Metadata itinerary
            $table->string('title')->default('Rencana Perjalanan Jogja');
            $table->integer('days');
            $table->json('interests')->nullable(); // ["kuliner", "sejarah"]
            $table->string('budget_type')->default('menengah'); // hemat, menengah, premium
            
            // Data itinerary lengkap (disimpan sebagai JSON)
            $table->json('itinerary_data');
            
            // Stats
            $table->integer('total_destinations')->default(0);
            $table->string('estimated_budget')->nullable();
            
            $table->timestamps();
            
            // Index untuk query cepat
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_itineraries');
    }
};