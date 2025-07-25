<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoServicioCliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'nombre',
        'descripcion',
        'cuenta_cliente_id',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuentaCliente(): BelongsTo
    {
        return $this->belongsTo(CuentaCliente::class);
    }
}
