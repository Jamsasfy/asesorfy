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
use App\Services\CorreccionVentaService;

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

  

   protected function afterSave(): void
{
    // Obtenemos los datos que se acaban de guardar y los que había antes
    $datosGuardados = $this->record->getAttributes();
    $datosOriginales = $this->record->getOriginal();

    // Comprobamos si se ha marcado la corrección como 'Completada'
    if (
        isset($datosGuardados['correccion_estado']) &&
        $datosGuardados['correccion_estado'] === VentaCorreccionEstadoEnum::COMPLETADA->value &&
        ($datosOriginales['correccion_estado'] ?? null) !== VentaCorreccionEstadoEnum::COMPLETADA->value
    ) {
        // --- INICIA EL PROCESO DE CORRECCIÓN ---
        try {
            CorreccionVentaService::procesar($this->record);

            Notification::make()
                ->title('¡Corrección Realizada con Éxito!')
                ->body('Se han anulado las facturas antiguas y generado las nuevas.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('¡Error al procesar la corrección!')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    } else {
        // --- LÓGICA PARA UNA EDICIÓN NORMAL (no una corrección) ---
        // Aquí se ejecuta tu código original si no es una corrección
        if ($this->record) {
            $this->record->updateTotal();
            $this->procesarFacturacionUnicaParaVenta($this->record);
        }
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