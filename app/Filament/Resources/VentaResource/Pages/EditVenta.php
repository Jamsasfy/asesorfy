<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Models\ClienteSuscripcion; // <-- Asegúrate de que esta línea esté
use App\Enums\ServicioTipoEnum;     // <-- Asegúrate de que esta línea esté
use App\Services\FacturacionService; // <-- Asegúrate de que esta línea esté
use Filament\Notifications\Notification; // <-- Asegúrate de que esta línea esté
use Filament\Resources\Pages\EditRecord;

// Modelos y Enums que ya tenías o necesitas importar (Filament 3 usa 'use Filament\Actions;' para DeleteAction)
use Filament\Actions;
use App\Enums\ClienteSuscripcionEstadoEnum; // Necesario para el Enum en la consulta
use App\Models\Venta; // Necesario para type-hinting si lo usas en closures
// Si usas Get/Set en tu afterSave, mantenlos:
use Filament\Forms\Get;
use Filament\Forms\Set;


class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    // Redirección después de actualizar (ya lo tenías)
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Tu método afterSave() que recalcula el total y lo guarda en la DB después de actualizar
    protected function afterSave(): void
    {
        if ($this->record) {
            // 1. (Esta línea ya la tenías)
            //    Asumimos que processSaleAfterCreation() no se llama aquí automáticamente,
            //    o que la lógica de la venta gestiona qué suscripciones existen/cambian.
            //    Aquí nos centramos en FACTURAR las únicas.
            $this->record->updateTotal(); // Asumiendo que este método existe en tu modelo Venta

            // --- NUEVA LÓGICA CLAVE AQUÍ! ---
            // 2. Después de que la Venta se ha actualizado, procesamos sus suscripciones únicas.
            $this->procesarFacturacionUnicaParaVenta($this->record);
            // --- FIN NUEVA LÓGICA CLAVE ---
        }
    }

    // Acciones de cabecera (ya las tenías)
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(), // Deja esto si ya lo tenías
        ];
    }

    /**
     * Método auxiliar para procesar las suscripciones únicas asociadas a una Venta.
     * Busca las suscripciones de tipo UNICO y ACTIVA y genera una factura PAGADA para cada una.
     * Se invoca después de crear o actualizar una Venta.
     */
    protected function procesarFacturacionUnicaParaVenta($venta): void
    {
        // Buscamos todas las ClienteSuscripcion que:
        // a) Están vinculadas a esta Venta (usando 'venta_origen_id').
        // b) Su Servicio asociado es de tipo UNICO.
        // c) Su estado es ACTIVA (indicando que aún no han sido facturadas y finalizadas).
        //    Esto es importante si se edita una Venta y se añade un servicio único,
        //    o si un servicio único estaba inactivo y se activa.
        $suscripcionesUnicasAFacturar = ClienteSuscripcion::query()
            ->where('venta_origen_id', $venta->id)
            ->whereHas('servicio', fn($q) => $q->where('tipo', ServicioTipoEnum::UNICO))
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA)
            ->get();

        foreach ($suscripcionesUnicasAFacturar as $suscripcion) {
            // Llamamos a nuestro servicio para generar la factura única.
            $factura = FacturacionService::generarFacturaParaSuscripcionUnica($suscripcion);

            if ($factura) {
                // Si la factura se generó con éxito, mostramos una notificación verde.
                Notification::make()
                    ->title("Factura {$factura->numero_factura} (Servicio Único) generada y marcada como PAGADA.")
                    ->success()
                    ->send();
            } else {
                // Si hubo un error, mostramos una notificación roja.
                Notification::make()
                    ->title("Error al generar factura para servicio único de suscripción ID {$suscripcion->id}.")
                    ->danger()
                    ->send();
            }
        }
    }
}