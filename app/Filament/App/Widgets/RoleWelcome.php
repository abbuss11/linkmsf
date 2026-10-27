<?php

namespace App\Filament\App\Widgets;

use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Tells each member what their role lets them do. Offers, applications and
 * evaluations arrive in later phases.
 */
class RoleWelcome extends Widget
{
    protected string $view = 'filament.app.widgets.role-welcome';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    // Rendered with the page: one request less on slow connections.
    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $organization = Filament::getTenant();
        $role = $user instanceof User && $organization instanceof Organization ? $user->roleIn($organization) : null;

        return [
            'name' => $user instanceof User ? $user->name : '',
            'organization' => $organization instanceof Organization ? $organization->name : '',
            'role' => $role,
        ];
    }
}
