<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->unique()->constrained()->cascadeOnDelete();
            // Working copy edited in the builder; frozen copies live in versions.
            $table->json('schema');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('application_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->char('schema_hash', 64);
            $table->timestamp('published_at');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_form_id', 'version']);
            $table->index('organization_id');
        });

        Schema::table('application_forms', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('application_form_versions')->nullOnDelete();
        });

        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('usage', 20);
            $table->string('field_key', 50)->nullable();
            $table->json('rule')->nullable();
            $table->unsignedTinyInteger('weight')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requirements');
        Schema::table('application_forms', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('application_form_versions');
        Schema::dropIfExists('application_forms');
    }
};
