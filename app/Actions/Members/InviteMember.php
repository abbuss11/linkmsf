<?php

namespace App\Actions\Members;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class InviteMember
{
    public function __construct(private readonly IssueInvitation $issueInvitation) {}

    public function handle(User $actor, Organization $organization, string $email, OrganizationRole $role): Invitation
    {
        Gate::forUser($actor)->authorize('create', [Invitation::class, $organization]);

        return $this->issueInvitation->handle($organization, $email, $role, $actor);
    }
}
