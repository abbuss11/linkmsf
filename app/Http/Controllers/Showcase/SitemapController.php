<?php

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Showcase\OpenJobPostings;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $postings = OpenJobPostings::query()->get(['slug', 'organization_id', 'updated_at']);
        $organizations = Organization::query()->whereIn('id', $postings->pluck('organization_id')->unique())->get(['slug', 'updated_at']);

        return response()
            ->view('showcase.sitemap', ['postings' => $postings, 'organizations' => $organizations])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
