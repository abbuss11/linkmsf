<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\UserType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demonstration data: 2 active organisations with one user per role, a user
 * belonging to both, a candidate and a pending organisation request.
 * Every account uses the password "password". Never run in production.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must never run in production.');
        }

        // MFA by email is pre-enabled for accounts that require it, so the
        // demo works without an authenticator app: codes land in the mail log.
        $superadmin = $this->user('Admin LINK', 'admin@link.test', mfaByEmail: true);
        $superadmin->is_superadmin = true;
        $superadmin->save();

        $sahel = Organization::factory()->create([
            'name' => 'Association Sahel Avenir',
            'slug' => 'sahel-avenir',
            'primary_color' => '#0f766e',
            'approved_by' => $superadmin->id,
        ]);

        $kawtal = Organization::factory()->create([
            'name' => 'Cabinet Kawtal Conseil',
            'slug' => 'kawtal-conseil',
            'primary_color' => '#1d4ed8',
            'approved_by' => $superadmin->id,
        ]);

        $this->member($sahel, $this->user('Aïcha Moussa', 'admin@sahel-avenir.test', mfaByEmail: true), OrganizationRole::OrgAdmin);
        $this->member($sahel, $this->user('Moussa Issoufou', 'recruteur@sahel-avenir.test'), OrganizationRole::Recruiter);
        $this->member($sahel, $this->user('Fatima Abdou', 'jury@sahel-avenir.test'), OrganizationRole::Jury);

        $this->member($kawtal, $this->user('Hadiza Garba', 'admin@kawtal-conseil.test', mfaByEmail: true), OrganizationRole::OrgAdmin);
        $this->member($kawtal, $this->user('Oumarou Sani', 'recruteur@kawtal-conseil.test'), OrganizationRole::Recruiter);
        $this->member($kawtal, $this->user('Mariama Boubacar', 'jury@kawtal-conseil.test'), OrganizationRole::Jury);

        // One person, two organisations, two different roles (§2.1).
        $multi = $this->user('Ibrahim Maïga', 'multi@link.test');
        $this->member($sahel, $multi, OrganizationRole::Recruiter);
        $this->member($kawtal, $multi, OrganizationRole::Jury);

        $this->user('Zeinabou Harouna', 'candidat@link.test', type: UserType::Candidate);

        OrganizationRequest::factory()->create([
            'organization_name' => 'Coopérative Tafadek',
            'contact_name' => 'Abdoulaye Ousmane',
            'email' => 'contact@tafadek.test',
            'message' => 'Nous recrutons 3 animateurs agricoles à Agadez.',
        ]);
    }

    private function user(string $name, string $email, UserType $type = UserType::Internal, bool $mfaByEmail = false): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'type' => $type,
            'password' => self::PASSWORD,
        ]);

        if ($mfaByEmail) {
            $user->has_email_authentication = true;
            $user->save();
        }

        return $user;
    }

    private function member(Organization $organization, User $user, OrganizationRole $role): void
    {
        OrganizationMembership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => MembershipStatus::Active,
        ]);
    }
}
