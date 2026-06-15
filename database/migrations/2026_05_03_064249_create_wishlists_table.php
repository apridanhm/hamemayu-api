<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('visited')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'content_id']); // Cegah duplikat
            $table->index(['user_id', 'visited']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};