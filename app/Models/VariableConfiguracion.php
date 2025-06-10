<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariableConfiguracion extends Model
{
    use HasFactory;

    // Indica el nombre de la tabla en la base de datos
    protected $table = 'variables_configuracion';

    // Define los campos que se pueden asignar masivamente (ej. al usar create() o update())
    protected $fillable = [
        'nombre_variable',
        'valor_variable',
        'tipo_dato',
        'descripcion',
        'es_secreto',
    ];

    // Define el tipo de dato para la columna 'es_secreto'
    protected $casts = [
        'es_secreto' => 'boolean',
    ];
}