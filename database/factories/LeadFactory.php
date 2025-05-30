<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\User;
use App\Enums\LeadEstadoEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'nombre'       => $this->faker->name,
            'email'        => $this->faker->unique()->safeEmail,
            'tfn'          => $this->faker->phoneNumber,
            'estado'       => LeadEstadoEnum::SIN_GESTIONAR->value,
            'asignado_id'  => User::factory(),   // crea un usuario y lo asigna
            // los demás campos pueden quedar null; la migración los acepta
        ];
    }
}
