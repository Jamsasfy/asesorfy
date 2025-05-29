<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trabajador extends Model
{
    protected $guarded = [];

      // Relación con el modelo User
      public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
      {
          return $this->belongsTo(\App\Models\User::class);
      }
  
      // Relación con el modelo Oficina
      public function oficina() :BelongsTo
      {
          return $this->belongsTo(Oficina::class);
      }
  
      public function departamentos(): BelongsToMany
      {
          return $this->belongsToMany(
              Departamento::class,
              'departamento_trabajador',
              'trabajador_id',
              'departamento_id'
          );
      }

      protected static function booted()
{
    static::deleting(function ($trabajador) {
        if ($trabajador->user) {
            $trabajador->user->delete();
        }
    });
}


}
