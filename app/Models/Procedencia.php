<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedencia extends Model
{
    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean', // <-- Añadir o asegurar que existe
    ];

    
}
