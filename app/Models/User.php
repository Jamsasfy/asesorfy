<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
         'acceso_app',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    //relacciones

    public function oficina() :BelongsTo{
        return $this->belongsTo(Oficina::class);
    }
     // Relación one-to-one con Trabajador
     public function trabajador() :HasOne
     {
         return $this->hasOne(Trabajador::class);
     }
   
     public function getFullNameAttribute(): string
     {
         $apellidos = $this->trabajador?->apellidos ?? '';
         return trim("{$this->name} {$apellidos}");
     }

     public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_user');
    }

    //usado para ver el tipo de usuario que ha subido el documento
    public function tipoDeUsuario(): string
{
    // Primero comprobamos si es super_admin (con Shield o Spatie)
    if ($this->hasRole('super_admin')) {
        return 'Super Admin';
    }

    // Luego comprobamos si es trabajador
    if ($this->trabajador) {
        return 'Trabajador';
    }

    // Luego si está vinculado a algún cliente
    if ($this->clientes()->exists()) {
        return 'Cliente';
    }

    return 'Desconocido';
}

  // Relación uno-a-muchos con Ventas (las ventas cerradas por este usuario)
  public function ventas(): HasMany
  {
      return $this->hasMany(Venta::class);
  }



}
