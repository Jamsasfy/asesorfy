<?php

namespace App\Filament\Widgets; // Confirma que este es el namespace correcto

use App\Models\Cliente;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Facades\Auth;

// No necesitamos Auth ni modelos para esta prueba súper simple

class AsesorTotalClientesWidget extends BaseWidget
{
        use HasWidgetShield;

    // protected static ?int $columns = 1; // Opcional, para forzar una columna si solo hay un stat


    protected function getStats(): array
    {
       /** @var User|null $asesor */ // Hacemos que $asesor pueda ser null inicialmente
        $asesor = Auth::user();

        // Si no hay un asesor autenticado, devolvemos stats vacíos o de error
        // para evitar errores al intentar acceder a $asesor->id.
        if (!$asesor) {
            return [
                Stat::make('Error', 'Usuario no autenticado')
                    ->description('No se pudieron cargar las estadísticas.')
                    ->color('danger'),
            ];
        }

        // Ahora que sabemos que $asesor no es null, podemos usar $asesor->id
        // 1. Total de clientes del asesor
        $totalSusClientes = Cliente::where('asesor_id', $asesor->id)->count();

        // 2. Clientes activos del asesor
        $susClientesActivos = Cliente::where('asesor_id', $asesor->id)
                                   ->where('estado', 'activo')
                                   ->count();

        // 3. Clientes del asesor que requieren atención o tienen impagos
        $susClientesAtencion = Cliente::where('asesor_id', $asesor->id)
                                     ->whereIn('estado', ['requiere_atencion', 'impagado'])
                                     ->count();

        // 4. Documentos por verificar (marcador de posición)
        $documentosPendientes = 0;

        return [
            Stat::make('Mis Clientes Totales', $totalSusClientes)
                ->description('Clientes actualmente asignados')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Mis Clientes Activos', $susClientesActivos)
                ->description('Clientes asignados en estado activo')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Clientes (Atención/Impago)', $susClientesAtencion)
                ->description('Requieren atención o con impagos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($susClientesAtencion > 0 ? 'danger' : 'success'),

            Stat::make('Documentos por Verificar', $documentosPendientes . ' (Próximamente)')
                ->description('Facturas, modelos, etc.')
                ->descriptionIcon('heroicon-m-document-magnifying-glass')
                ->color('warning'),
        ];
    }
}