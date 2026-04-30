<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->string('street_view_id')->nullable()->after('cover_image')
                ->comment('Google Street View panorama ID untuk virtual tour');
            $table->string('google_maps_url')->nullable()->after('street_view_id')
                ->comment('URL Google Maps untuk fallback');
        });
    }
    
    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn(['street_view_id', 'google_maps_url']);
        });
    }
};
