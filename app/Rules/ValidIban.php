<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Iban\Validation\Validator;
use Iban\Validation\Iban;

class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = new Validator();

        $iban = new Iban(str_replace(' ', '', $value)); // quitamos espacios si los hay

        if (! $validator->validate($iban)) {
            $fail('El :attribute no es un IBAN válido.');
        }
    }
}
