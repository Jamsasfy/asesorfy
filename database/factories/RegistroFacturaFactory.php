<?php

namespace Database\Factories;

use App\Enums\EstadoRegistroFactura;
use App\Enums\MedioDePago;
use App\Enums\TipoRegistroFactura;
use App\Models\RegistroFactura;
use App\Models\RegistroFacturaLinea;
use App\Models\Tercero;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistroFacturaFactory extends Factory
{
    public function definition(): array
    {
        // Ya no definimos 'cliente_id' aquí. Se lo pasaremos desde el comando.
        return [
            'tipo' => fake()->randomElement(TipoRegistroFactura::cases()),
            'estado' => fake()->randomElement(EstadoRegistroFactura::cases()),
            'fecha_expedicion' => fake()->dateTimeBetween('-2 years', 'now'),
            'numero_factura' => fake()->unique()->bothify('FAC-####/'.date('Y')),
            'medio' => fake()->randomElement(MedioDePago::cases()),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RegistroFactura $registro) {
            // 1. Buscamos o creamos un Tercero PARA EL CLIENTE de esta factura.
            $tercero = Tercero::query()
                ->where('cliente_id', $registro->cliente_id)
                ->inRandomOrder()
                ->first()
                ?? Tercero::factory()->create(['cliente_id' => $registro->cliente_id]);

            // 2. Rellenamos los datos snapshot y los totales
            if ($registro->tipo === TipoRegistroFactura::EMITIDA) {
                $lineas = RegistroFacturaLinea::factory()->count(3)->create(['registro_factura_id' => $registro->id]);
                $totalBase = $lineas->sum('subtotal');
                $totalIva = $lineas->sum(fn ($l) => $l->subtotal * ($l->porcentaje_iva / 100));
                
                $registro->update([
                    'tercero_id' => $tercero->id,
                    'tercero_nombre' => $tercero->nombre,
                    'tercero_nif' => $tercero->nif,
                    'base_imponible' => $totalBase,
                    'cuota_iva' => $totalIva,
                    'total_factura' => $totalBase + $totalIva,
                ]);
            } else { // Si es RECIBIDA
                $base = fake()->randomFloat(2, 50, 1000);
                $iva = $base * 0.21;
                
                $registro->update([
                    'tercero_id' => $tercero->id,
                    'tercero_nombre' => $tercero->nombre,
                    'tercero_nif' => $tercero->nif,
                    'base_imponible' => $base,
                    'cuota_iva' => $iva,
                    'total_factura' => $base + $iva,
                ]);
            }
        });
    }
}