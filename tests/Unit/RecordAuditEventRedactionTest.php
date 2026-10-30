<?php

use App\Actions\Audit\RecordAuditEvent;

it('redacts secrets at any depth, whatever the case of the key', function () {
    $redacted = RecordAuditEvent::redact([
        'email' => 'a@b.test',
        'Password' => 'hunter2',
        'nested' => [
            'api_key' => 'k',
            'remember_token' => 't',
            'invitation_token' => 'x',
            'keep' => 'yes',
            'deeper' => ['client_secret' => 's'],
        ],
        'recovery_codes' => ['a', 'b'],
    ]);

    expect($redacted)->toBe([
        'email' => 'a@b.test',
        'Password' => '[redacted]',
        'nested' => [
            'api_key' => '[redacted]',
            'remember_token' => '[redacted]',
            'invitation_token' => '[redacted]',
            'keep' => 'yes',
            'deeper' => ['client_secret' => '[redacted]'],
        ],
        'recovery_codes' => '[redacted]',
    ]);
});
