<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\OrganizationRequestStatus;
use App\Enums\OrganizationStatus;
use App\Enums\UserType;
use App\Models\Organization;
use App\Models\OrganizationRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Global indicators for the platform team (§8.2).
 */
class PlatformOverview extends StatsOverviewWidget
{
    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $organizations = Organization::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $users = User::query()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            Stat::make(__('dashboard.platform.active_organizations'), (int) ($organizations[OrganizationStatus::Active->value] ?? 0))
                ->description(__('dashboard.platform.suspended', ['count' => (int) ($organizations[OrganizationStatus::Suspended->value] ?? 0)])),
            Stat::make(
                __('dashboard.platform.pending_requests'),
                OrganizationRequest::query()->where('status', OrganizationRequestStatus::Pending->value)->count(),
            ),
            Stat::make(__('dashboard.platform.internal_users'), (int) ($users[UserType::Internal->value] ?? 0)),
            Stat::make(__('dashboard.platform.candidates'), (int) ($users[UserType::Candidate->value] ?? 0)),
            Stat::make(__('dashboard.platform.failed_jobs'), DB::table('failed_jobs')->count())
                ->color('danger'),
        ];
    }
}
