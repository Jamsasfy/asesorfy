<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $guarded = [];

   // Un departamento es coordinado por un usuario
public function coordinador(): BelongsTo
{
    return $this->belongsTo(User::class, 'coordinador_id');
}

// Un departamento tiene muchos trabajadores
public function trabajadores(): HasMany
{
    return $this->hasMany(Trabajador::class, 'departamento_id');
}

}
