<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Departamento extends Model
{
    protected $guarded = [];

     // Relación inversa many-to-many (si deseas acceder a los usuarios asociados)
     public function users() :BelongsToMany
     {
         return $this->belongsToMany(User::class, 'departamento_user', 'departamento_id', 'user_id');
     }


}
