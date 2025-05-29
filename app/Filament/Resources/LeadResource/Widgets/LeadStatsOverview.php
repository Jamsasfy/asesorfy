<?php

namespace App\Filament\Resources\LeadResource\Widgets;

use App\Enums\LeadEstadoEnum;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStatsOverview extends BaseWidget
{
    protected static ?int $sort = -2;
    protected static ?string $pollingInterval = '60s';

    //protected static ?string $heading = 'Estadísticas de Leads';
    //protected static ?string $description = 'Resumen de los estados de los leads';
    protected static ?string $maxWidth = '5xl'; // Cambiamos el ancho máximo a 5xl
    protected static ?string $maxHeight = '5xl'; // Cambiamos la altura máxima a 5xl
   
    // Forzamos 5 columnas
    

    protected function getStats(): array
    {
        $leads = \App\Models\Lead::query()->get(); // 👈 aquí nos aseguramos de tener los datos cargados

        $totalLeads       = Lead::count();
        $pendientes       = Lead::whereNull('asignado_id')->count();
          // Decide descripción y color según pendientes
        $descripcion  = $pendientes > 0
            ? "Pendientes de asignar: {$pendientes}"
            : "No hay leads por asignar";
        $colorEstad  = $pendientes > 0
            ? 'warning'
            : 'success';

        $iniciales = collect(LeadEstadoEnum::cases())->filter(fn ($e) => $e->isInicial())->pluck('value')->toArray();
        $enProgreso = collect(LeadEstadoEnum::cases())->filter(fn ($e) => $e->isEnProgreso())->pluck('value')->toArray();
        $convertidos = collect(LeadEstadoEnum::cases())->filter(fn ($e) => $e->isConvertido())->pluck('value')->toArray();
        
        return [
            Stat::make('Leads Totales', $totalLeads)
            ->description($descripcion)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color($colorEstad),

        
            Stat::make('Iniciales', \App\Models\Lead::whereIn('estado', $iniciales)->count())
                ->description('Aún sin gestionar')
                ->descriptionIcon('heroicon-m-eye')
                ->color('gray'),
        
            Stat::make('En Proceso', \App\Models\Lead::whereIn('estado', $enProgreso)->count())
                ->description('Trabajándose')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),
        
            Stat::make('Convertidos', \App\Models\Lead::whereIn('estado', $convertidos)->count())
                ->description('Leads cerrados con éxito')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
          
        ];
    }

}
