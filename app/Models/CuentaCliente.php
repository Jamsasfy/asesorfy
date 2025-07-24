<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public static function generarSiguienteCodigo(string $codigoBase, int $clienteId): string
{
    // Buscar todas las cuentas de ese cliente que empiecen con ese prefijo
    $subcuentas = self::where('cliente_id', $clienteId)
        ->where('codigo', 'like', $codigoBase . '%')
        ->pluck('codigo');

    // Obtener los sufijos numéricos actuales
    $sufijos = $subcuentas
        ->map(fn($codigo) => intval(substr($codigo, strlen($codigoBase))))
        ->filter() // elimina nulls
        ->sort()
        ->values();

    // Calcular siguiente sufijo disponible
    $siguiente = $sufijos->isEmpty() ? 1 : $sufijos->max() + 1;

    // Formatear con ceros a la izquierda (hasta 3 cifras, ej. 004)
    $sufijoFormateado = str_pad($siguiente, 3, '0', STR_PAD_LEFT);

    return $codigoBase . $sufijoFormateado;
}


}
