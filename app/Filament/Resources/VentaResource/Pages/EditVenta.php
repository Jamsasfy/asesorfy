<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Models\ClienteSuscripcion;
use App\Enums\ServicioTipoEnum;
use App\Services\FacturacionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\VentaCorreccionEstadoEnum; // <-- Importante añadir este
use App\Models\Venta;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    // Redirección después de actualizar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Acciones de cabecera
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    //==================================================================
    // ▼▼▼ MÉTODO NUEVO: Se ejecuta ANTES de guardar los cambios ▼▼▼
    //==================================================================
    protected function beforeSave(): void
    {
        // Comprobamos si el campo 'correccion_estado' existe en los datos del formulario
        if (!isset($this->data['correccion_estado'])) {
            return; // Si no existe, no hacemos nada
        }

        // Obtenemos el estado de corrección que viene del formulario
        $nuevoEstado = $this->data['correccion_estado'];

        // Obtenemos el estado original que tenía la venta antes de cualquier cambio
        $estadoOriginal = $this->record->getOriginal('correccion_estado');

        // Comprobamos si el estado ha cambiado a 'COMPLETADA'
        if ($nuevoEstado === VentaCorreccionEstadoEnum::COMPLETADA->value && $estadoOriginal !== VentaCorreccionEstadoEnum::COMPLETADA->value) {

            // Aquí es donde llamaremos a la lógica pesada en el futuro.
            // Por ahora, solo ponemos una notificación para confirmar que funciona.
            
            // Futura llamada: CorreccionVentaService::procesar($this->record);

            Notification::make()
                ->title('¡Corrección Procesada!')
                ->body('La lógica de anulación y creación de facturas se ejecutaría aquí.')
                ->success()
                ->send();
        }
    }

    //==================================================================
    // ▼▼▼ Tu lógica existente: Se ejecuta DESPUÉS de guardar ▼▼▼
    //==================================================================
    protected function afterSave(): void
    {
        if ($this->record) {
            // 1. Actualiza el total de la venta
            $this->record->updateTotal();

            // 2. Procesa la facturación de servicios únicos (si se añadió alguno nuevo)
            $this->procesarFacturacionUnicaParaVenta($this->record);
        }
    }

    /**
     * Tu método auxiliar existente para facturar servicios únicos.
     */
    protected function procesarFacturacionUnicaParaVenta($venta): void
    {
        $suscripcionesUnicasAFacturar = ClienteSuscripcion::query()
            ->where('venta_origen_id', $venta->id)
            ->whereHas('servicio', fn($q) => $q->where('tipo', ServicioTipoEnum::UNICO))
            ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
            ->get();

        foreach ($suscripcionesUnicasAFacturar as $suscripcion) {
            $factura = FacturacionService::generarFacturaParaSuscripcionUnica($suscripcion);

            if ($factura) {
                Notification::make()
                    ->title("Factura {$factura->numero_factura} (Servicio Único) generada y marcada como PAGADA.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title("Error al generar factura para servicio único de suscripción ID {$suscripcion->id}.")
                    ->danger()
                    ->send();
            }
        }
    }
}