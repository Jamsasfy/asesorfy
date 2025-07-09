<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Models\ClienteSuscripcion; // <-- Asegúrate de que esta línea esté
use App\Enums\ServicioTipoEnum;     // <-- Asegúrate de que esta línea esté
use App\Services\FacturacionService; // <-- Asegúrate de que esta línea esté
use Filament\Notifications\Notification; // <-- Asegúrate de que esta línea esté
use Filament\Resources\Pages\CreateRecord;

// Modelos y Enums que ya tenías o necesitas importar:
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Models\Proyecto;
use App\Models\Servicio;
use App\Models\Venta; // Necesario para type-hinting si lo usas en closures
use Filament\Actions;
use Filament\Notifications\Actions\Action as NotifyAction; // Si usas este alias


class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    // Redirección después de crear (ya lo tenías)
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Este método se ejecuta automáticamente DESPUÉS de que la Venta se ha creado en la base de datos.
     */
    protected function afterCreate(): void
    {
        // Aseguramos que el registro de la venta se ha creado correctamente
        if ($this->record) {

            // 1. (Esta línea ya la tenías y es CLAVE)
            // Llama al método en el modelo Venta que crea los Proyectos y Suscripciones.
            $this->record->processSaleAfterCreation(); 

            // 2. Actualiza el importe_total en el modelo Venta.
            //    Se ejecuta DESPUÉS del paso 1 para asegurar que las suscripciones (con sus subtotales) están creadas.
            $this->record->updateTotal();

            // --- ¡NUEVA LÓGICA CLAVE AQUÍ! ---
            // 3. Después de crear las suscripciones y actualizar el total,
            //    procesamos la facturación de los servicios únicos de esta Venta.
            $this->procesarFacturacionUnicaParaVenta($this->record);
            // --- FIN NUEVA LÓGICA CLAVE ---


            // 4. Muestra una notificación SI esta venta en particular generó proyectos.
            //    (Esta parte ya la tenías)
            if ($this->record->proyectos()->exists()) {
                Notification::make()
                    ->warning()
                    ->title('Proyectos y Suscripciones Creadas')
                    ->body('Se han generado proyectos de activación. Algunas suscripciones pueden estar pendientes hasta que se completen.')
                    ->send();
            }

            // 5. Actualiza el estado del Lead asociado a esta venta.
            //    (Esta parte ya la tenías)
            if ($this->record->lead_id && $this->record->lead) {
                $this->record->lead->update([
                    'estado' => \App\Enums\LeadEstadoEnum::CONVERTIDO,
                ]);
            }
        }
    }

    /**
     * Método auxiliar para procesar las suscripciones únicas asociadas a una Venta.
     * Busca las suscripciones de tipo UNICO y ACTIVA y genera una factura PAGADA para cada una.
     * Este método es llamado después de crear o actualizar una Venta.
     */
    protected function procesarFacturacionUnicaParaVenta($venta): void
    {
        // Buscamos todas las ClienteSuscripcion que:
        // a) Están vinculadas a esta Venta (usando 'venta_origen_id').
        // b) Su Servicio asociado es de tipo UNICO.
        // c) Su estado es ACTIVA (indicando que aún no han sido facturadas y finalizadas).
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