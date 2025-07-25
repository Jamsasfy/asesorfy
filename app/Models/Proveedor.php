<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\TipoNifProveedor;
use App\Enums\TipoOperacionProveedor;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'nif',
        'tipo_nif',
        'prefijo_intracomunitario',
        'direccion',
        'codigo_postal',
        'ciudad',
        'provincia',
        'pais',
        'email',
        'email_secundario',
        'telefono',
        'persona_contacto',
        'tambien_cliente',
        'tipo_operacion',
        'cuenta_contable_id',
    ];

    protected $casts = [
        'tipo_nif' => TipoNifProveedor::class,
        'tipo_operacion' => TipoOperacionProveedor::class,
        'tambien_cliente' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

   public function cuentaContable(): BelongsTo
{
    return $this->belongsTo(CuentaCliente::class, 'cuenta_contable_id');
}
}

