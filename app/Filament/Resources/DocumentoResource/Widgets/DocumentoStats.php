<?php

namespace App\Filament\Resources\DocumentoResource\Widgets;

use App\Models\Documento;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;


class DocumentoStats extends BaseWidget
{

   // use HasWidgetShield;


    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return [Stat::make('Error', 'Usuario no autenticado')->color('danger')];
        }

        // Para depurar roles (puedes descomentar si es necesario):
        // dd('Usuario en Widget:', $user->email, 'Roles:', $user->getRoleNames()->toArray());

        $baseQuery = Documento::query();
        $tituloDescripcionTotal = "del total de documentos en AsesorFy";
        $statTitleTotalDocumentos = 'Total Documentos Sistema';

        if ($user->hasRole('super_admin')) {
            // El super_admin ve todo, $baseQuery ya es global.
            // No se necesitan más filtros para super_admin aquí.
        } elseif ($user->hasRole('asesor')) {
            // Si NO es super_admin PERO SÍ es asesor, filtramos.
            $baseQuery->whereHas('cliente', function (Builder $query) use ($user) {
                $query->where('asesor_id', $user->id);
            });
            $tituloDescripcionTotal = "de tus clientes asignados";
            $statTitleTotalDocumentos = 'Mis Documentos Totales';
        }
       


        $total = (clone $baseQuery)->count();
        $verificados = (clone $baseQuery)->where('verificado', true)->count();
        $noVerificados = $total - $verificados;

        $porcentajeVerificados = $total > 0 ? round(($verificados / $total) * 100, 1) : 0;
        $porcentajeNoVerificados = $total > 0 ? (100 - $porcentajeVerificados) : 0;
        if ($total > 0 && ($porcentajeVerificados + $porcentajeNoVerificados) > 100) {
            $porcentajeNoVerificados = floor(100 - $porcentajeVerificados);
        }
        if($total == 0) {
            $porcentajeVerificados = 0;
            $porcentajeNoVerificados = 0;
        }

        return [
            Stat::make('✅ Verificados', $verificados)
                ->description("{$porcentajeVerificados}% {$tituloDescripcionTotal}")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('⚠️ No verificados', $noVerificados)
                ->description("{$porcentajeNoVerificados}% {$tituloDescripcionTotal}")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($noVerificados > 0 ? 'danger' : 'success'),

            Stat::make($statTitleTotalDocumentos, $total)
                ->description($user->hasRole('asesor') ? 'Documentos de tus clientes' : 'Documentos subidos al sistema')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),

            Stat::make('📊 % Sin verificar', $porcentajeNoVerificados . '%')
                ->description($tituloDescripcionTotal)
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($noVerificados > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getColumns(): int
    {
        return 4; // Puedes ajustar el layout (1, 2, 3, 4...)
    }

    


}
