<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Processing state and filter columns (§5.1, §5.2). The filter columns
     * are copied from the frozen snapshot at submission, so that filters and
     * sorting run on indexed columns, never on JSON scans.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('pipeline_stage_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->timestamp('stage_changed_at')->nullable()->after('pipeline_stage_id');
            $table->string('eligibility', 20)->nullable()->after('stage_changed_at');
            $table->timestamp('eligibility_checked_at')->nullable()->after('eligibility');
            $table->unsignedTinyInteger('documents_received')->default(0)->after('eligibility_checked_at');
            $table->unsignedTinyInteger('documents_expected')->default(0)->after('documents_received');
            $table->string('candidate_name')->nullable()->after('documents_expected');
            $table->string('city', 120)->nullable()->after('candidate_name');
            $table->unsignedTinyInteger('education_rank')->nullable()->after('city');
            $table->unsignedTinyInteger('years_of_experience')->nullable()->after('education_rank');
            $table->json('languages')->nullable()->after('years_of_experience');
            $table->json('skills')->nullable()->after('languages');
            $table->timestamp('last_activity_at')->nullable()->after('skills');

            $table->index(['organization_id', 'job_posting_id', 'pipeline_stage_id', 'submitted_at'], 'applications_org_posting_stage_index');
            $table->index(['organization_id', 'job_posting_id', 'eligibility'], 'applications_org_posting_eligibility_index');
            $table->index(['organization_id', 'job_posting_id', 'education_rank'], 'applications_org_posting_education_index');
            $table->index(['organization_id', 'job_posting_id', 'years_of_experience'], 'applications_org_posting_experience_index');
            $table->index(['organization_id', 'job_posting_id', 'city'], 'applications_org_posting_city_index');
            $table->index(['organization_id', 'job_posting_id', 'candidate_name'], 'applications_org_posting_name_index');
            $table->fullText('candidate_name', 'applications_candidate_name_fulltext');
        });

        // Multi-valued indexes: JSON_OVERLAPS(languages, ...) uses them.
        DB::statement('CREATE INDEX applications_languages_index ON applications ((CAST(languages AS CHAR(60) ARRAY)))');
        DB::statement('CREATE INDEX applications_skills_index ON applications ((CAST(skills AS CHAR(60) ARRAY)))');

        Schema::table('application_answers', function (Blueprint $table) {
            $table->fullText('value_text', 'answers_value_text_fulltext');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->fullText('extracted_text', 'documents_extracted_text_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', fn (Blueprint $table) => $table->dropFullText('documents_extracted_text_fulltext'));
        Schema::table('application_answers', fn (Blueprint $table) => $table->dropFullText('answers_value_text_fulltext'));
        DB::statement('DROP INDEX applications_skills_index ON applications');
        DB::statement('DROP INDEX applications_languages_index ON applications');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropFullText('applications_candidate_name_fulltext');
            foreach (['stage', 'eligibility', 'education', 'experience', 'city', 'name'] as $index) {
                $table->dropIndex('applications_org_posting_'.$index.'_index');
            }
            $table->dropConstrainedForeignId('pipeline_stage_id');
            $table->dropColumn([
                'stage_changed_at', 'eligibility', 'eligibility_checked_at', 'documents_received', 'documents_expected',
                'candidate_name', 'city', 'education_rank', 'years_of_experience', 'languages', 'skills', 'last_activity_at',
            ]);
        });
    }
};
