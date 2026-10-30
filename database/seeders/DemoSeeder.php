<?php

namespace Database\Seeders;

use App\Actions\JobPostings\CreateJobPostingDraft;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\SaveApplicationForm;
use App\Actions\JobPostings\SaveJobRequirements;
use App\Actions\JobPostings\UpdateApplicationSettings;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\UserType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Demonstration data: 2 active organisations with one user per role, a user
 * belonging to both, a candidate, a pending organisation request and offers.
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

        // No e-mails for demo data (approval requests, etc.).
        Notification::fake();

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

        $this->member($sahel, $sahelAdmin = $this->user('Aïcha Moussa', 'admin@sahel-avenir.test', mfaByEmail: true), OrganizationRole::OrgAdmin);
        $this->member($sahel, $sahelRecruiter = $this->user('Moussa Issoufou', 'recruteur@sahel-avenir.test'), OrganizationRole::Recruiter);
        $this->member($sahel, $this->user('Fatima Abdou', 'jury@sahel-avenir.test'), OrganizationRole::Jury);

        $this->member($kawtal, $this->user('Hadiza Garba', 'admin@kawtal-conseil.test', mfaByEmail: true), OrganizationRole::OrgAdmin);
        $this->member($kawtal, $kawtalRecruiter = $this->user('Oumarou Sani', 'recruteur@kawtal-conseil.test'), OrganizationRole::Recruiter);
        $this->member($kawtal, $this->user('Mariama Boubacar', 'jury@kawtal-conseil.test'), OrganizationRole::Jury);

        // One person, two organisations, two different roles (§2.1).
        $multi = $this->user('Ibrahim Maïga', 'multi@link.test');
        $this->member($sahel, $multi, OrganizationRole::Recruiter);
        $this->member($kawtal, $multi, OrganizationRole::Jury);

        $this->user('Zeinabou Harouna', 'candidat@link.test', type: UserType::Candidate);

        $this->jobPostings($sahel, $sahelRecruiter, $kawtal, $kawtalRecruiter);

        OrganizationRequest::factory()->create([
            'organization_name' => 'Coopérative Tafadek',
            'contact_name' => 'Abdoulaye Ousmane',
            'email' => 'contact@tafadek.test',
            'message' => 'Nous recrutons 3 animateurs agricoles à Agadez.',
        ]);
    }

    /**
     * Offers built through the real Actions: a published offer with its form
     * and criteria, a draft, and an offer waiting for approval.
     */
    private function jobPostings(Organization $sahel, User $sahelRecruiter, Organization $kawtal, User $kawtalRecruiter): void
    {
        $context = app(OrganizationContext::class);

        $context->runFor($sahel, function () use ($sahel, $sahelRecruiter): void {
            $posting = app(CreateJobPostingDraft::class)->handle($sahelRecruiter, $sahel, [
                'type' => 'employment',
                'title' => 'Chargé(e) de suivi-évaluation',
                'description' => '<p>Sahel Avenir recrute un(e) chargé(e) de suivi-évaluation pour ses projets d’éducation à Tillabéri.</p><ul><li>Collecte et analyse des données</li><li>Rapports trimestriels</li></ul>',
                'location' => 'Tillabéri',
                'contract_type' => 'fixed_term',
                'education_level' => 'licence',
                'experience_years_min' => 2,
                'skills' => ['KoboToolbox', 'Excel', 'Rédaction'],
                'places' => 1,
                'closes_at' => now()->addDays(21)->setTime(18, 0)->toDateTimeString(),
            ]);

            app(SaveApplicationForm::class)->handle($sahelRecruiter, $posting, [
                ['type' => 'section', 'label' => 'Vos informations'],
                ['type' => 'short_text', 'label' => 'Nom complet', 'required' => true, 'profile_field' => 'full_name'],
                ['type' => 'phone', 'label' => 'Téléphone', 'required' => true, 'profile_field' => 'phone'],
                ['type' => 'select', 'label' => 'Niveau d’études', 'required' => true, 'profile_field' => 'education_level'],
                ['type' => 'number', 'label' => 'Années d’expérience en suivi-évaluation', 'required' => true, 'validation' => ['min' => 0, 'max' => 40]],
                ['type' => 'checkbox_list', 'label' => 'Langues parlées', 'options' => [
                    ['value' => 'fr', 'label' => 'Français'], ['value' => 'ha', 'label' => 'Haoussa'], ['value' => 'dje', 'label' => 'Zarma'],
                ]],
                ['type' => 'yes_no', 'key' => 'mobilite', 'label' => 'Acceptez-vous des missions sur le terrain ?', 'required' => true],
                ['type' => 'long_text', 'label' => 'Pourquoi ce poste ?', 'required' => true, 'validation' => ['max_words' => 250]],
            ]);

            app(SaveJobRequirements::class)->handle($sahelRecruiter, $posting->fresh(), [
                ['label' => 'Licence au minimum', 'usage' => 'eliminatory', 'field_key' => 'niveau_detudes', 'rule' => ['operator' => 'education_at_least', 'value' => 'licence']],
                ['label' => 'Disponible pour le terrain', 'usage' => 'eliminatory', 'field_key' => 'mobilite', 'rule' => ['operator' => 'is_true']],
                ['label' => 'Expérience en suivi-évaluation', 'usage' => 'scored', 'weight' => 40],
                ['label' => 'Maîtrise de KoboToolbox', 'usage' => 'desired'],
            ]);

            app(UpdateApplicationSettings::class)->handle($sahelRecruiter, $posting->fresh(), [
                'edit_after_submit' => 'until_close',
                'duplicate_policy' => 'single',
                'notify_new_applications' => 'daily',
                'instructions' => 'Préparez votre CV au format PDF avant de commencer.',
                'confirmation_message' => 'Merci, votre candidature a bien été reçue.',
                'documents' => [
                    ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 5120, 'max_files' => 1],
                    ['label' => 'Diplômes', 'required' => false, 'extensions' => ['pdf', 'jpg', 'png'], 'max_kb' => 5120, 'max_files' => 3],
                ],
            ]);

            app(PublishJobPosting::class)->handle($sahelRecruiter, $posting->fresh());

            app(CreateJobPostingDraft::class)->handle($sahelRecruiter, $sahel, [
                'type' => 'internship',
                'title' => 'Stage en communication digitale',
                'location' => 'Niamey',
            ]);
        });

        // Kawtal validates offers before publication.
        $kawtal->settings = array_merge($kawtal->settings ?? [], ['job_postings' => ['requires_approval' => true, 'recruiters_see_all' => true]]);
        $kawtal->save();

        $context->runFor($kawtal, function () use ($kawtal, $kawtalRecruiter): void {
            $posting = app(CreateJobPostingDraft::class)->handle($kawtalRecruiter, $kawtal, [
                'type' => 'consultancy',
                'title' => 'Consultant(e) en évaluation de projet',
                'location' => 'Niamey',
                'closes_at' => now()->addDays(14)->setTime(17, 0)->toDateTimeString(),
            ]);
            app(SaveApplicationForm::class)->handle($kawtalRecruiter, $posting, [
                ['type' => 'short_text', 'label' => 'Nom complet', 'required' => true, 'profile_field' => 'full_name'],
                ['type' => 'url', 'label' => 'Lien vers un rapport publié'],
            ]);
            app(PublishJobPosting::class)->handle($kawtalRecruiter, $posting->fresh());
        });
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
