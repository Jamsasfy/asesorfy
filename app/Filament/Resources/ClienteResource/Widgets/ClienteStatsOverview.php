<?php

namespace App\Filament\Resources\ClienteResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClienteStatsOverview extends BaseWidget
{
    protected static ?int $columns = 4;
    protected static ?int $rows    = 2;


    protected function getStats(): array
    {
       // 1) Calculamos totales y grupos
    $total           = \App\Models\Cliente::count();
    $sinAsignar      = \App\Models\Cliente::whereNull('asesor_id')->count();
    $activo      = \App\Models\Cliente::where('estado', 'activo')->count();
    $atencionImpago  = \App\Models\Cliente::whereIn('estado', ['requiere_atencion', 'impagado'])->count();
    $bloqueados     = \App\Models\Cliente::where('estado', 'bloqueado')->count();
    $rescindido     = \App\Models\Cliente::where('estado', 'rescindido')->count();
    $baja     = \App\Models\Cliente::where('estado', 'baja')->count();

    // 2) Construimos los Stat
    return [
        Stat::make('Sin asignar', $sinAsignar)
            ->description("de {$total} clientes totales en AsesorFy")                     // Muestra “de X”
            ->descriptionIcon('heroicon-m-adjustments-horizontal')
            ->color($sinAsignar > 0 ? 'warning' : 'success'), // Amarillo si hay sin asignar, verde si 0

        Stat::make('Clientes en estado Activo', $activo)
          
            ->descriptionIcon('heroicon-m-clock')
            ->color('success'), 

        Stat::make('Requiere Atención / Impagados', $atencionImpago)
            ->description($atencionImpago > 0 ? 'Impagados o que requieren atención' : 'No hay impagados o que requieran atención')
            ->descriptionIcon('heroicon-m-exclamation-circle')
            ->color($atencionImpago > 0 ? 'danger' : 'success'),

        Stat::make('Bloqueado', $bloqueados)
            ->description($bloqueados > 0 ? 'Bloqueados' : 'Todo al dia 😎')
            ->descriptionIcon('heroicon-m-x-circle')
            ->color($bloqueados > 0 ? 'danger' : 'success'),

        Stat::make('Rescindidos', $rescindido)
            ->description($rescindido > 0 ? 'Rescindidos' : 'Todo al dia 😎')
            ->descriptionIcon('heroicon-m-x-circle')
            ->color($rescindido > 0 ? 'danger' : 'success'),
        Stat::make('Clientes en estado de Baja', $baja)
            ->description($baja > 0 ? 'Bajas' : 'Todo al dia 😎')
            ->descriptionIcon('heroicon-m-x-circle')
            ->color($baja > 0 ? 'danger' : 'success'), 
       
    ];
    }
}
