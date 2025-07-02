<?php

namespace App\Filament\Resources\ProyectoResource\Widgets;

use App\Enums\ProyectoEstadoEnum;
use App\Models\Proyecto;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder; // Importamos Builder
use Illuminate\Support\Facades\Auth; // Importamos Auth

class ProyectoStatsOverview extends BaseWidget
{
    // Método auxiliar para obtener la consulta base de proyectos
    // Se filtra por usuario si es asesor
    protected function getProjectsBaseQuery(): Builder
    {
        $query = Proyecto::query(); // Inicia una nueva consulta para el modelo Proyecto

        // Si el usuario autenticado tiene el rol 'asesor',
        // filtra los proyectos por su ID asignado.
        if (Auth::check() && Auth::user()->hasRole('asesor')) {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }

      protected function getStats(): array
    {
        $baseQuery = $this->getProjectsBaseQuery();

        // --- Lógica para el subtítulo de la tarjeta 'Pendientes' ---
        $descripcionPendientes = '';
        if (Auth::check() && !Auth::user()->hasRole('asesor')) {
            // ▼▼▼ CAMBIO CLAVE AQUÍ ▼▼▼
            // Ahora contamos todos los proyectos sin asesor, sin importar su estado.
            $pendientesSinAsignar = Proyecto::whereNull('user_id')->count();
            
            $descripcionPendientes = $pendientesSinAsignar . ' sin asignar en total'; 
        }

        return [
            // Tarjeta 1: Pendientes
            Stat::make('Pendientes', (clone $baseQuery)->where('estado', ProyectoEstadoEnum::Pendiente)->count())
                ->description($descripcionPendientes)
                ->descriptionIcon('heroicon-m-inbox')
                ->color('primary'),

            // Tarjeta 2: En Progreso
            Stat::make('En Progreso', (clone $baseQuery)->where('estado', ProyectoEstadoEnum::EnProgreso)->count())
                ->description('Actualmente en curso')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),

            // Tarjeta 3: Finalizados
            Stat::make('Finalizados', (clone $baseQuery)->where('estado', ProyectoEstadoEnum::Finalizado)->count())
                ->description('Proyectos completados')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            // Tarjeta 4: Cancelados
            Stat::make('Cancelados', (clone $baseQuery)->where('estado', ProyectoEstadoEnum::Cancelado)->count())
                ->description('Proyectos cancelados')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
    
}