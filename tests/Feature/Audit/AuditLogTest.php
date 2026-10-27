<?php

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\AuditLog;

it('records login successes and failures without the password', function () {
    $candidate = makeCandidate(['email' => 'c@example.test']);

    $this->post('/connexion', ['email' => 'c@example.test', 'password' => 'wrong-secret-123']);
    $this->post('/connexion', ['email' => 'c@example.test', 'password' => 'password']);

    $failed = AuditLog::query()->where('event', AuditEvent::LoginFailed->value)->sole();
    $succeeded = AuditLog::query()->where('event', AuditEvent::LoginSucceeded->value)->sole();

    expect($failed->properties->all())->toBe(['email' => 'c@example.test'])
        ->and($succeeded->causer_id)->toBe($candidate->id)
        ->and($succeeded->ip)->toBe('127.0.0.1')
        ->and(AuditLog::query()->get()->toJson())->not->toContain('wrong-secret-123')
        ->and($candidate->fresh()->last_login_at)->not->toBeNull();
});

it('redacts secrets passed by mistake', function () {
    $log = app(RecordAuditEvent::class)->handle(
        AuditEvent::OrganizationUpdated,
        properties: ['name' => 'X', 'password' => 'secret', 'token' => 'abc'],
    );

    // MySQL may reorder JSON keys: compare content, not order.
    expect($log->fresh()->properties->all())->toEqual(['name' => 'X', 'password' => '[redacted]', 'token' => '[redacted]']);
});

it('cannot be modified or deleted', function () {
    $log = app(RecordAuditEvent::class)->handle(AuditEvent::OrganizationUpdated);

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class)
        ->and($log->fresh()->event)->toBe(AuditEvent::OrganizationUpdated->value);
});
