<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * New-account fields are only required for a guest.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'token' => ['required', 'string', 'size:64'],
        ];

        if ($this->user() === null) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['password'] = ['required', 'string', 'confirmed', Password::defaults()];
        }

        return $rules;
    }
}
