<?php

namespace App\Actions\Candidates;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a global candidate account. Input is validated by the FormRequest;
 * the verification email is sent through the Registered event.
 */
class RegisterCandidate
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'type' => UserType::Candidate,
            ]);

            $this->audit->handle(AuditEvent::CandidateRegistered, subject: $user, causer: $user);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
