<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'year']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            // Nullable for imported applications (phase 6).
            $table->foreignId('candidate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('application_form_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 40)->nullable();
            $table->string('status', 20);
            $table->string('public_status', 20)->nullable();
            $table->json('profile_snapshot')->nullable();
            $table->string('source', 60)->nullable();
            $table->unsignedTinyInteger('last_step')->default(1);
            $table->timestamp('consent_at')->nullable();
            $table->text('consent_text')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('draft_reminded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One application per candidate and offer (§9.1).
            $table->unique(['job_posting_id', 'candidate_user_id']);
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'job_posting_id', 'status', 'submitted_at'], 'applications_org_posting_status_index');
            $table->index(['candidate_user_id', 'status']);
        });

        Schema::create('application_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 50);
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->decimal('value_number', 14, 2)->nullable();
            $table->date('value_date')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'field_key']);
            // Filters of phase 4 start with the organisation (§9.1).
            $table->index(['organization_id', 'field_key', 'value_number'], 'answers_org_key_number_index');
            $table->index(['organization_id', 'field_key', 'value_date'], 'answers_org_key_date_index');
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 50);
            $table->string('status', 20);
            $table->string('disk', 20);
            $table->string('path')->nullable();
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size');
            $table->unsignedInteger('received_bytes')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'application_id', 'field_key'], 'app_documents_org_app_key_index');
        });

        Schema::create('application_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_form_version_id')->nullable()->constrained()->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['organization_id', 'application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_revisions');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_answers');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('organization_counters');
    }
};
