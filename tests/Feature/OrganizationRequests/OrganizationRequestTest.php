<?php

use App\Actions\Organizations\ApproveOrganizationRequest;
use App\Enums\OrganizationRequestStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Filament\Admin\Resources\OrganizationRequests\Pages\ListOrganizationRequests;
use App\Http\Middleware\ProtectAgainstSpam;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationRequest;
use App\Notifications\MemberInvited;
use App\Notifications\NewOrganizationRequest;
use App\Notifications\OrganizationRequestRejected;
use App\Services\AntiSpam\CaptchaVerifier;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->superadmin = makeSuperadmin();
});

function organizationRequestPayload(array $overrides = []): array
{
    return array_merge([
        'organization_name' => 'Coopérative Tafadek',
        'contact_name' => 'Abdoulaye Ousmane',
        'email' => 'Contact@Tafadek.test',
        'phone' => '+227 90 12 34 56',
        'country' => 'NE',
        'message' => 'Nous recrutons des animateurs.',
        ProtectAgainstSpam::TRAP_FIELD => '',
        ProtectAgainstSpam::TIME_FIELD => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
    ], $overrides);
}

it('records a public request and notifies superadmins', function () {
    $this->post('/demander-un-espace', organizationRequestPayload())
        ->assertRedirect(route('organization-requests.thanks'));

    $request = OrganizationRequest::query()->sole();
    expect($request->email)->toBe('contact@tafadek.test')
        ->and($request->status)->toBe(OrganizationRequestStatus::Pending);

    Notification::assertSentTo($this->superadmin, NewOrganizationRequest::class);
});

it('rejects bots that fill the honeypot', function () {
    $this->post('/demander-un-espace', organizationRequestPayload([ProtectAgainstSpam::TRAP_FIELD => 'http://spam.test']))
        ->assertSessionHasErrors('form');

    expect(OrganizationRequest::query()->count())->toBe(0);
});

it('rejects forms submitted too fast or with a forged timestamp', function (Closure $override) {
    $this->post('/demander-un-espace', organizationRequestPayload($override()))->assertSessionHasErrors('form');

    expect(OrganizationRequest::query()->count())->toBe(0);
})->with([
    'too fast' => [fn () => [ProtectAgainstSpam::TIME_FIELD => Crypt::encryptString((string) time())]],
    'forged timestamp' => [fn () => [ProtectAgainstSpam::TIME_FIELD => '12345']],
]);

it('validates every field on the server', function () {
    $this->post('/demander-un-espace', organizationRequestPayload([
        'organization_name' => '',
        'email' => 'not-an-email',
        'phone' => '<script>',
        'country' => 'XX',
    ]))->assertSessionHasErrors(['organization_name', 'email', 'phone', 'country']);
});

it('requires the captcha when Turnstile is enabled', function () {
    $this->app->instance(CaptchaVerifier::class, new class implements CaptchaVerifier
    {
        public function isEnabled(): bool
        {
            return true;
        }

        public function verify(?string $token, ?string $ip): bool
        {
            return $token === 'valid';
        }
    });

    $this->post('/demander-un-espace', organizationRequestPayload())->assertSessionHasErrors('cf-turnstile-response');
    $this->post('/demander-un-espace', organizationRequestPayload(['cf-turnstile-response' => 'forged']))->assertSessionHasErrors('cf-turnstile-response');
    $this->post('/demander-un-espace', organizationRequestPayload(['cf-turnstile-response' => 'valid']))->assertRedirect(route('organization-requests.thanks'));
});

it('refuses a second pending request from the same address', function () {
    $this->post('/demander-un-espace', organizationRequestPayload());
    $this->post('/demander-un-espace', organizationRequestPayload())->assertSessionHasErrors('email');

    expect(OrganizationRequest::query()->count())->toBe(1);
});

it('limits the number of requests per IP address', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/demander-un-espace', organizationRequestPayload(['email' => "c{$i}@example.test"]));
    }

    $this->post('/demander-un-espace', organizationRequestPayload(['email' => 'c6@example.test']))->assertTooManyRequests();
});

it('creates the organisation and invites the requester when a superadmin approves', function () {
    $request = OrganizationRequest::factory()->create(['email' => 'boss@tafadek.test', 'organization_name' => 'Tafadek']);
    actingOnPlatform($this->superadmin);

    Livewire::test(ListOrganizationRequests::class)
        ->callAction(TestAction::make('approve')->table($request), data: ['slug' => 'tafadek'])
        ->assertNotified(__('organization_requests.notifications.approved'));

    $organization = Organization::query()->where('slug', 'tafadek')->sole();
    $invitation = Invitation::queryForOrganization($organization)->sole();

    expect($organization->status)->toBe(OrganizationStatus::Active)
        ->and($request->fresh()->status)->toBe(OrganizationRequestStatus::Approved)
        ->and($request->fresh()->organization_id)->toBe($organization->id)
        ->and($invitation->email)->toBe('boss@tafadek.test')
        ->and($invitation->role)->toBe(OrganizationRole::OrgAdmin);

    Notification::assertSentTo(new AnonymousNotifiable, MemberInvited::class);
});

it('refuses reserved or duplicate slugs on approval', function (string $slug) {
    makeOrganization(['slug' => 'pris']);
    $request = OrganizationRequest::factory()->create();
    actingOnPlatform($this->superadmin);

    Livewire::test(ListOrganizationRequests::class)
        ->callAction(TestAction::make('approve')->table($request), data: ['slug' => $slug])
        ->assertHasActionErrors(['slug']);

    expect($request->fresh()->status)->toBe(OrganizationRequestStatus::Pending);
})->with(['login', 'pris', 'Majuscules', 'a b']);

it('rejects a request with a reason sent to the requester', function () {
    $request = OrganizationRequest::factory()->create(['email' => 'no@example.test']);
    actingOnPlatform($this->superadmin);

    Livewire::test(ListOrganizationRequests::class)
        ->callAction(TestAction::make('reject')->table($request), data: ['reason' => 'Structure non identifiée.']);

    expect($request->fresh()->status)->toBe(OrganizationRequestStatus::Rejected)
        ->and($request->fresh()->rejection_reason)->toBe('Structure non identifiée.');

    Notification::assertSentTo(new AnonymousNotifiable, OrganizationRequestRejected::class);
});

it('lets only superadmins review requests', function () {
    $orgadmin = memberOf(makeOrganization(), OrganizationRole::OrgAdmin);

    app(ApproveOrganizationRequest::class)->handle($orgadmin, OrganizationRequest::factory()->create(), 'nouvelle');
})->throws(AuthorizationException::class);
