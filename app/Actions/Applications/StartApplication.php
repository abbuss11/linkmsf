<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Exceptions\ActionFailed;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Postuler": creates the draft, or resumes it (on any device), or reopens
 * a submitted application the offer allows to modify (§4.4, §12 duplicate rule).
 */
class StartApplication
{
    public function handle(User $candidate, JobPosting $posting, ?string $source = null): Application
    {
        if (! $candidate->isCandidate() || ! $candidate->hasVerifiedEmail()) {
            throw ActionFailed::because('applications.errors.candidate_only');
        }

        if (! $posting->acceptsApplicationsAt(now())) {
            throw ActionFailed::because('applications.errors.closed');
        }

        $existing = Application::forCandidate($candidate)->where('job_posting_id', $posting->getKey())->first();

        if ($existing !== null) {
            return match (true) {
                $existing->status === ApplicationStatus::Withdrawn => throw ActionFailed::because('applications.errors.withdrawn'),
                $existing->canStillBeChanged() => $this->rebind($existing, $posting),
                default => throw ActionFailed::because('applications.errors.already_applied'),
            };
        }

        try {
            return DB::transaction(function () use ($candidate, $posting, $source) {
                $application = new Application([
                    'job_posting_id' => $posting->getKey(),
                    'candidate_user_id' => $candidate->getKey(),
                    'application_form_version_id' => $this->currentVersionId($posting),
                    'status' => ApplicationStatus::Draft,
                    'source' => self::cleanSource($source),
                ]);
                $application->organization_id = $posting->organization_id;
                $application->save();

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            // Two tabs clicked "Postuler" at once: resume the other one's draft.
            return Application::forCandidate($candidate)->where('job_posting_id', $posting->getKey())->firstOrFail();
        }
    }

    /**
     * Drafts and modifications always use the form currently published.
     */
    private function rebind(Application $application, JobPosting $posting): Application
    {
        $application->application_form_version_id = $this->currentVersionId($posting);
        $application->save();

        return $application;
    }

    private function currentVersionId(JobPosting $posting): int
    {
        $versionId = ApplicationForm::queryAcrossOrganizations()
            ->where('job_posting_id', $posting->getKey())
            ->value('current_version_id');

        if ($versionId === null) {
            throw ActionFailed::because('applications.errors.closed');
        }

        return (int) $versionId;
    }

    /**
     * Diffusion channel from ?source= (§4.1): short, simple identifier only.
     */
    public static function cleanSource(?string $source): ?string
    {
        $clean = substr((string) preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) $source)), 0, 60);

        return $clean !== '' ? $clean : null;
    }
}
