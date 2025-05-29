<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comentario extends Model
{
    use SoftDeletes;

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
        // En el futuro:
         \App\Models\Lead::class => 'Lead',
        // \App\Models\Proyecto::class => 'Proyecto',
    ];
}
}
