<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{

    protected $guarded = [];

    public function comentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
//mas modelos en la busqueda en comentarios recurso de admin
    public static function getComentableModels(): array
{
    return [
        \App\Models\Cliente::class => 'Cliente',
       
         \App\Models\Lead::class => 'Lead',
       
         \App\Models\Proyecto::class => 'Proyecto',
    ];
}
}
