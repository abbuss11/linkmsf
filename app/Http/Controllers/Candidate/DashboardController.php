<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $applications = Application::forCandidate($user)
            ->with('jobPosting')
            ->latest('updated_at')
            ->get();
        $organizations = Organization::query()
            ->whereIn('id', $applications->pluck('organization_id')->unique())
            ->pluck('name', 'id');

        return view('candidate.dashboard', [
            'user' => $user,
            'drafts' => $applications->filter->isDraft()->values(),
            'sent' => $applications->reject->isDraft()->values(),
            'organizations' => $organizations,
        ]);
    }
}
