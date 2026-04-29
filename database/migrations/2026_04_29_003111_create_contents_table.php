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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable(); // deskripsi singkat di card
            $table->longText('content'); // artikel lengkap (bisa HTML)
            $table->decimal('lat', 10, 8)->nullable(); // untuk peta
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('ticket_price')->nullable();
            $table->string('cover_image')->nullable(); // URL gambar utama
            $table->boolean('is_featured')->default(false); // untuk highlight di homepage
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
