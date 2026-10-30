<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ATS data (§5): stage history, eligibility results, tags, notes, lists,
     * saved filters and exports. All isolated by organisation.
     */
    public function up(): void
    {
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            // Names kept as they were: stages can be renamed later.
            $table->string('from_stage_name', 80)->nullable();
            $table->string('to_stage_name', 80);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin', 20);
            $table->text('reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at');

            $table->index(['organization_id', 'application_id', 'created_at'], 'histories_org_application_index');
        });

        Schema::create('eligibility_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('field_key', 50)->nullable();
            $table->json('rule')->nullable();
            $table->string('outcome', 20);
            $table->text('value')->nullable();
            $table->timestamp('evaluated_at');

            $table->index(['organization_id', 'application_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('application_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'tag_id']);
            $table->index(['organization_id', 'tag_id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility', 20);
            $table->text('body');
            $table->timestamps();

            $table->index(['organization_id', 'application_id']);
        });

        Schema::create('shortlists', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('kind', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'job_posting_id']);
        });

        Schema::create('shortlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shortlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('reason')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shortlist_id', 'application_id']);
            $table->index(['organization_id', 'shortlist_id', 'position']);
        });

        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'job_posting_id']);
        });

        Schema::create('application_exports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shortlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('format', 10);
            $table->string('status', 20);
            $table->json('criteria');
            $table->unsignedInteger('row_count')->nullable();
            $table->string('disk', 20)->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['application_exports', 'saved_filters', 'shortlist_items', 'shortlists', 'notes', 'application_tag', 'tags', 'eligibility_results', 'application_status_histories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
