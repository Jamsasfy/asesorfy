<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NombreOrazonSocial implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nombre = trim(request('nombre'));
        $apellidos = trim(request('apellidos'));
        $razon = trim(request('razon_social'));

        // Si NO hay razón social y falta nombre o apellidos
        if (empty($razon) && (empty($nombre) || empty($apellidos))) {
            $fail('Debes rellenar nombre y apellidos o razón social.');
        }
    }
}
