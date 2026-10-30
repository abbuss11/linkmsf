<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('about')->nullable()->after('secondary_color');
            $table->string('website')->nullable()->after('about');
        });

        Schema::table('job_postings', function (Blueprint $table) {
            // Plain text copy of the searchable content (title excluded), kept in sync by the model.
            $table->longText('search_text')->nullable()->after('missions');
            $table->string('share_image_path')->nullable()->after('settings');
            $table->fullText(['title', 'search_text']);
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropFullText(['title', 'search_text']);
            $table->dropColumn(['search_text', 'share_image_path']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['about', 'website']);
        });
    }
};
