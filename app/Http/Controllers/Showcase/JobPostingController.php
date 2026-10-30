<?php

namespace App\Http\Controllers\Showcase;

use App\Actions\Applications\StartApplication;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationStatus;
use App\Forms\Dynamic\FormSchema;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\Organization;
use App\Models\User;
use App\Support\Showcase\JobPostingStructuredData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public page of an offer (§4.1). Closed or suspended offers keep their page
 * (links shared on WhatsApp keep working) but cannot be applied to.
 */
class JobPostingController extends Controller
{
    public function __invoke(Request $request, string $slug): View
    {
        $posting = JobPosting::queryAcrossOrganizations()
            ->where('slug', $slug)
            ->whereIn('status', [JobPostingStatus::Published->value, JobPostingStatus::Suspended->value, JobPostingStatus::Closed->value])
            ->firstOrFail();

        $organization = Organization::query()
            ->whereKey($posting->organization_id)
            ->where('status', OrganizationStatus::Active->value)
            ->firstOrFail();

        if (filled($source = StartApplication::cleanSource($request->query('source')))) {
            $request->session()->put('application_source.'.$posting->getKey(), $source);
        }

        $form = ApplicationForm::queryForOrganization($organization)->where('job_posting_id', $posting->getKey())->with('currentVersion')->first();
        $user = $request->user();

        return view('showcase.job-posting', [
            'posting' => $posting,
            'organization' => $organization,
            'requirements' => JobRequirement::queryForOrganization($organization)
                ->where('job_posting_id', $posting->getKey())
                ->orderBy('position')
                ->get(),
            'documents' => FormSchema::fromArray($form?->currentVersion?->schema)->documentFields(),
            'open' => $posting->acceptsApplicationsAt(now()),
            'application' => $user instanceof User && $user->isCandidate()
                ? Application::forCandidate($user)->where('job_posting_id', $posting->getKey())->first()
                : null,
            'structuredData' => $posting->status === JobPostingStatus::Published ? JobPostingStructuredData::json($posting, $organization) : null,
        ]);
    }
}
