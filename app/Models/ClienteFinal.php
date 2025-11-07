<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteFinal extends Model
{
    use HasFactory;

protected $table = 'clientes_finales'; // ✅ con "s" en ambos

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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}