<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title');
            // Unique across the platform: public URL /offres/{slug} (§4.1).
            $table->string('slug', 160)->unique();
            $table->string('reference', 40);
            $table->longText('description')->nullable();
            $table->text('missions')->nullable();
            $table->string('location')->nullable();
            $table->string('contract_type', 30)->nullable();
            $table->string('education_level', 20)->nullable();
            $table->unsignedTinyInteger('experience_years_min')->nullable();
            $table->json('skills')->nullable();
            $table->unsignedInteger('places')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status', 30);
            $table->json('settings')->nullable();
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status', 'closes_at']);
            // Used by the scheduled closing task across organisations.
            $table->index(['status', 'closes_at']);
        });

        Schema::create('job_posting_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_posting_id', 'user_id']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_assignments');
        Schema::dropIfExists('job_postings');
    }
};
