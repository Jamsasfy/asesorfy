<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\RegistroFactura;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Factories\Sequence;

class SeedClientInvoicesCommand extends Command
{
    /**
     * La firma del comando. Aquí defines el nombre y los argumentos.
     * {cliente_id} : Un argumento obligatorio.
     * {--count=20} : Una opción, si no se especifica, su valor por defecto será 20.
     */
    protected $signature = 'seed:client-invoices {cliente_id} {--count=20}';

    /**
     * La descripción del comando, aparecerá cuando ejecutes `php artisan list`.
     */
    protected $description = 'Crea registros de factura falsos para un cliente específico.';

    /**
     * La lógica del comando va aquí.
     */
    public function handle(): int
    {

         // AÑADE ESTE BLOQUE AL PRINCIPIO
    if (app()->isProduction()) {
        $this->error('¡PELIGRO! Este comando está deshabilitado en el entorno de producción.');
        return Command::FAILURE;
    }

    
        $clienteId = $this->argument('cliente_id');
        $count = $this->option('count');

        // 1. Verificamos que el cliente exista para evitar errores
        if (! $cliente = Cliente::find($clienteId)) {
            $this->error("Error: No se encontró ningún cliente con el ID {$clienteId}.");
            return Command::FAILURE;
        }

        $this->info("Creando {$count} registros de factura para el cliente: {$cliente->nombre} (ID: {$clienteId})...");

        // 2. Usamos la factory para crear los registros
        RegistroFactura::factory()
            ->count((int) $count)
            ->create([
                'cliente_id' => $clienteId, // Sobrescribimos el cliente_id para todos los registros
            ]);

        $this->info("✅ ¡Proceso completado! Se han creado {$count} registros.");

        return Command::SUCCESS;
    }
}