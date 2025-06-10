<?php

namespace App\Services;

use App\Models\VariableConfiguracion;
use Illuminate\Support\Facades\Crypt; // Usaremos el cifrado de Laravel
use Illuminate\Support\Facades\Log; // Para registrar errores si algo falla

class ConfiguracionService
{
    /**
     * Obtiene el valor de una variable de configuración.
     * Desencripta si es necesario y castea al tipo de dato correcto.
     *
     * @param string $nombreVariable El nombre de la variable a buscar.
     * @param mixed $default Valor por defecto a devolver si la variable no existe o hay un error.
     * @return mixed El valor de la variable con el tipo de dato correcto.
     */
    public static function get(string $nombreVariable, mixed $default = null): mixed
    {
        $variable = VariableConfiguracion::where('nombre_variable', $nombreVariable)->first();

        if (!$variable) {
            return $default; // La variable no existe, devuelve el valor por defecto
        }

        $valor = $variable->valor_variable;

        // Si la variable está marcada como secreto, intentamos descifrarla
        if ($variable->es_secreto) {
            try {
                $valor = Crypt::decryptString($valor);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Si falla el descifrado (ej. clave de la app cambiada), registramos el error
                Log::error("Error al descifrar la variable '{$nombreVariable}': " . $e->getMessage());
                return $default; // Y devolvemos el valor por defecto por seguridad
            }
        }

        // Castear el valor al tipo de dato especificado en la base de datos
        return match ($variable->tipo_dato) {
            'numero_entero' => (int) $valor,
            'numero_decimal' => (float) $valor,
            'booleano' => (bool) filter_var($valor, FILTER_VALIDATE_BOOLEAN), // Manejo robusto para 'true', 'false', '1', '0'
            default => (string) $valor, // Por defecto, lo tratamos como cadena
        };
    }

    /**
     * Guarda o actualiza una variable de configuración.
     * Cifra el valor si la variable se marca como secreta.
     *
     * @param string $nombreVariable Nombre único de la variable.
     * @param mixed $valor El valor a guardar (se cifrará si es secreto).
     * @param string $tipoDato Tipo de dato ('cadena', 'numero_entero', etc.).
     * @param string|null $descripcion Descripción de la variable.
     * @param bool $esSecreto Si es TRUE, el valor se cifrará.
     * @return VariableConfiguracion La instancia del modelo guardado o actualizado.
     */
    public static function set(
        string $nombreVariable,
        mixed $valor,
        string $tipoDato,
        ?string $descripcion = null,
        bool $esSecreto = false
    ): VariableConfiguracion {
        // Convertimos el valor a string antes de operar con él, para el cifrado y guardado en DB
        $valorParaGuardar = (string) $valor;

        // Si es un secreto, ciframos el valor antes de guardarlo en la base de datos
        if ($esSecreto) {
            $valorParaGuardar = Crypt::encryptString($valorParaGuardar);
        }

        // Busca la variable por su nombre; si no existe, la crea.
        return VariableConfiguracion::updateOrCreate(
            ['nombre_variable' => $nombreVariable],
            [
                'valor_variable' => $valorParaGuardar,
                'tipo_dato'      => $tipoDato,
                'descripcion'    => $descripcion,
                'es_secreto'     => $esSecreto,
            ]
        );
    }
}