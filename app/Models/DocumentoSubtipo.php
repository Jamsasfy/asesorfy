<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoSubtipo extends Model
{
    protected $fillable = [
        'documento_categoria_id',
        'nombre',
        'descripcion',
        'activo',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(DocumentoCategoria::class, 'documento_categoria_id');
    }

   


}
