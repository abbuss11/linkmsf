<?php

namespace App\Http\Controllers\Showcase;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Showcase\OpenJobPostings;
use Illuminate\View\View;

/**
 * Career page of one organisation (§4.1), including offers it chose not to
 * list on the common job board.
 */
class CareerPageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->where('status', OrganizationStatus::Active->value)
            ->firstOrFail();

        return view('showcase.career-page', [
            'organization' => $organization,
            'postings' => OpenJobPostings::query()
                ->where('organization_id', $organization->getKey())
                ->orderBy('closes_at')
                ->get(),
        ]);
    }
}
