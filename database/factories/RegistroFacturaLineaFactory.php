<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RegistroFacturaLineaFactory extends Factory
{
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 5);
        $precioUnitario = fake()->randomFloat(2, 10, 500);
        $subtotal = $cantidad * $precioUnitario;

        return [
            'descripcion' => fake()->sentence(3),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'porcentaje_iva' => 21.00,
            'subtotal' => $subtotal,
        ];
    }
}