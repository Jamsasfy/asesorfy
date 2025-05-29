<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cliente extends Model
{

    protected $fillable = [
        'user_id',
        'tipo_cliente_id',
        'nombre',
        'apellidos',
        'razon_social',
        'dni_cif',
        'email_contacto',
        'telefono_contacto',
        'direccion',
        'codigo_postal',
        'localidad',
        'provincia',
        'comunidad_autonoma',
        'iban_asesorfy',
        'iban_impuestos',
        'ccc',
        'asesor_id',
        'coordinador_id',
        'observaciones',
        'estado',
        'fecha_alta',
        'fecha_baja',
        'lead_id',
        'comercial_id',
       
    ];


    public function user()
{
    return $this->belongsTo(User::class);
}

public function tipoCliente()
{
    return $this->belongsTo(TipoCliente::class, 'tipo_cliente_id');
}

public function asesor()
{
    return $this->belongsTo(User::class, 'asesor_id');
}

public function coordinador()
{
    return $this->belongsTo(User::class, 'coordinador_id');
}

public function usuarios(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'cliente_user');
}

public function recordTitle(): string
{
    return $this->razon_social ?? 'Cliente sin nombre';
}

public function documentos(): HasMany
{
    return $this->hasMany(\App\Models\Documento::class);
}

public function comentarios(): MorphMany
{
    return $this->morphMany(Comentario::class, 'comentable');
}

public function lead(): BelongsTo
{
    return $this->belongsTo(Lead::class);
}

 // Relación uno-a-muchos con Ventas (todas las ventas de este cliente)
 public function ventas(): HasMany
 {
     return $this->hasMany(Venta::class);
 }

   

}
