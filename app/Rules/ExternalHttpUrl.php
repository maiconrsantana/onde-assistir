<?php

namespace App\Rules;

use App\Support\ExternalUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExternalHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ExternalUrl::isValid($value)) {
            $fail('Informe uma URL externa válida usando http ou https.');
        }
    }
}
