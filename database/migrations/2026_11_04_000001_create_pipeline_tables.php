<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recruitment pipelines (§5.4): one template per organisation
     * (job_posting_id null) copied into each offer, which may then adapt it.
     */
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'job_posting_id']);
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            // Stable identifier: system stages keep theirs (received, ineligible…).
            $table->string('key', 40);
            $table->string('name', 80);
            $table->string('kind', 20);
            $table->string('public_status', 20);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_system')->default(false);
            $table->boolean('sends_message')->default(false);
            $table->timestamps();

            $table->unique(['pipeline_id', 'key']);
            $table->index(['organization_id', 'pipeline_id', 'position']);
        });

        Schema::create('pipeline_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['from_stage_id', 'to_stage_id']);
            $table->index(['organization_id', 'pipeline_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_transitions');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
