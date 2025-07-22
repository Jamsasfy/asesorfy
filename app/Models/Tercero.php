<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tercero extends Model
{
    use HasFactory;

    protected $table = 'terceros';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'nif',
        'direccion',
        'codigo_postal',
        'ciudad',
        'provincia',
        'pais',
        'email',
        'telefono',
    ];

    /**
     * Un tercero pertenece a un Cliente de AsesorFy.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}