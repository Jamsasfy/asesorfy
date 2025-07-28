<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;


class CuentaCliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'cuenta_catalogo_id',
        'codigo',
        'descripcion',
        'es_activa',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuentaCatalogo(): BelongsTo
    {
        return $this->belongsTo(CuentaCatalogo::class);
    }

    public function getCodigoPrefijoAttribute(): ?string
{
    return substr($this->codigo, 0, 4);
}

public function getCodigoSufijoAttribute(): ?string
{
    return substr($this->codigo, 4);
}



/* public static function generarSiguienteCodigo(string $codigoBase, int $clienteId): string
{
    // 1. Buscar todas las cuentas del cliente con ese prefijo base
    $subcuentas = self::where('cliente_id', $clienteId)
        ->where('codigo', 'like', $codigoBase . '%')
        ->pluck('codigo');

    // 2. Obtener sufijos numéricos de 8 cifras (como enteros)
    $sufijos = $subcuentas
        ->map(fn($codigo) => intval(substr($codigo, strlen($codigoBase), 8)))
        ->filter(fn($val) => $val > 0) // solo positivos, elimina null/0
        ->sort()
        ->values();

    // 3. Calcular el siguiente sufijo: si no hay, es el 1; si hay, el máximo + 1
    $siguiente = $sufijos->isEmpty() ? 1 : $sufijos->max() + 1;

    // 4. Formatear con ceros a la izquierda (8 cifras)
    $sufijoFormateado = str_pad($siguiente, 8, '0', STR_PAD_LEFT);

    // 5. Unir base + sufijo
    return $codigoBase . $sufijoFormateado;
} */

public static function generarSiguienteCodigoCombinado(string $codigoBaseCompleto, int $clienteId): string
{
    $codigoPrefijo = substr($codigoBaseCompleto, 0, 4);

    $catalogoSubcuentas = CuentaCatalogo::where('codigo', 'like', $codigoPrefijo . '%')->pluck('codigo');

    $clienteSubcuentas = self::where('cliente_id', $clienteId)
        ->where('codigo', 'like', $codigoPrefijo . '%')
        ->pluck('codigo');

    $todosCodigos = $catalogoSubcuentas->merge($clienteSubcuentas);

    $sufijos = $todosCodigos
        ->map(fn($codigo) => intval(substr($codigo, strlen($codigoPrefijo), 8)))
        ->filter(fn($val) => $val > 0)
        ->sort()
        ->values();

    $siguiente = $sufijos->isEmpty() ? 1 : $sufijos->max() + 1;

    $sufijoFormateado = str_pad($siguiente, 8, '0', STR_PAD_LEFT);

    return $codigoPrefijo . $sufijoFormateado;
}








}
