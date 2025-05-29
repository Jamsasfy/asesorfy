<?php

namespace App\Filament\Resources\DocumentoResource\Widgets;

use App\Models\Documento;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


class DocumentoStats extends BaseWidget
{

    use HasWidgetShield;


    protected function getStats(): array
    {
        $total = Documento::count();
    $verificados = Documento::where('verificado', true)->count();
    $noVerificados = $total - $verificados;

    $porcentajeVerificados = $total > 0 ? round(($verificados / $total) * 100, 1) : 0;
    $porcentajeNoVerificados = 100 - $porcentajeVerificados;
        return [
            Stat::make('✅ Verificados', $verificados)
            ->description("{$porcentajeVerificados}% del total")
            ->descriptionIcon('heroicon-m-check-badge')
            ->color('success'),

        Stat::make('⚠️ No verificados', $noVerificados)
            ->description("{$porcentajeNoVerificados}% del total")
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color('danger'),

        Stat::make('📄 Total documentos', $total)
            ->description('Documentos subidos')
            ->descriptionIcon('heroicon-m-folder')
            ->color('primary'),

            Stat::make('📊 % Sin verificar', $porcentajeNoVerificados . '%')
            ->description('del total de documentos')
            ->descriptionIcon('heroicon-m-exclamation-circle')
            ->color('danger'),

        ];
    }

    protected function getColumns(): int
    {
        return 4; // Puedes ajustar el layout (1, 2, 3, 4...)
    }

    


}
