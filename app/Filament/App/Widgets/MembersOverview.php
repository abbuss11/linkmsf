<?php

namespace App\Filament\App\Widgets;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\Permission;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Team overview for orgadmins. Queries rely on the organisation scope.
 */
class MembersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasPermissionIn(app(OrganizationContext::class)->id(), Permission::ViewMembers);
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $active = OrganizationMembership::query()->where('status', MembershipStatus::Active->value);

        return [
            Stat::make(__('dashboard.members.active'), (clone $active)->count()),
            Stat::make(__('dashboard.members.recruiters'), (clone $active)->where('role', OrganizationRole::Recruiter->value)->count()),
            Stat::make(__('dashboard.members.jury'), (clone $active)->where('role', OrganizationRole::Jury->value)->count()),
            Stat::make(__('dashboard.members.pending_invitations'), Invitation::query()->pending()->count()),
        ];
    }
}
