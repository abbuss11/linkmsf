<?php

namespace App\Rules;

use App\Services\AntiSpam\CaptchaVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Captcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CaptchaVerifier::class)->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail(__('anti_spam.captcha_failed'));
        }
    }
}
