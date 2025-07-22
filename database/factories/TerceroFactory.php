<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TerceroFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Asegúrate de que el cliente con ID 1 existe, o pon uno que exista.
            'cliente_id' => 73,
            'nombre' => fake()->company(),
            'nif' => fake()->unique()->numerify('########A'),
            'direccion' => fake()->streetAddress(),
            'codigo_postal' => fake()->postcode(),
            'ciudad' => fake()->city(),
            'provincia' => fake()->state(),
            'pais' => 'España',
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
        ];
    }
}