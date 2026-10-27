<?php

namespace App\Http\Requests;

use App\Rules\Captcha;
use App\Services\AntiSpam\CaptchaVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_filter([
            'organization_name' => ['required', 'string', 'min:2', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{6,32}$/'],
            'country' => ['required', 'string', Rule::in(array_keys((array) __('countries')))],
            'message' => ['nullable', 'string', 'max:2000'],
            'cf-turnstile-response' => app(CaptchaVerifier::class)->isEnabled() ? ['required', new Captcha] : null,
        ]);
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            // The form pre-fills "+227 ": treat it as empty.
            'phone' => in_array($phone, ['', '+227'], true) ? null : $phone,
        ]);
    }
}
