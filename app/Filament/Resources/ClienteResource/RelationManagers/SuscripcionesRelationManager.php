<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\ClienteSuscripcion;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Filament\Resources\ProyectoResource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Database\Eloquent\Builder;

class SuscripcionesRelationManager extends RelationManager
{
    protected static string $relationship = 'suscripciones';
    protected static ?string $title = 'Suscripciones y Servicios Contratados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('servicio.nombre')
            ->columns([
                Tables\Columns\TextColumn::make('servicio.nombre')->label('Servicio'),
                
                Tables\Columns\TextColumn::make('estado')->badge()
                    ->formatStateUsing(fn (ClienteSuscripcionEstadoEnum $state) => $state->getLabel())
                    ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
                        ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                        ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                        ClienteSuscripcionEstadoEnum::CANCELADA, ClienteSuscripcionEstadoEnum::FINALIZADA => 'danger',
                        default => 'gray',
                    }),

                // ▼▼▼ NUEVA COLUMNA INTELIGENTE ▼▼▼
                Tables\Columns\TextColumn::make('dependencia_proyecto')
                    ->label('Dependencia')
                    ->html()
                    ->state(function (ClienteSuscripcion $record): ?string {
                        // Solo mostramos algo si la suscripción está pendiente
                        if ($record->estado !== ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION) {
                            return '---';
                        }

                        // Buscamos los proyectos no finalizados de la misma venta
                        $proyectosPendientes = $record->ventaOrigen?->proyectos()
                            ->whereNotIn('estado', [ProyectoEstadoEnum::Finalizado, ProyectoEstadoEnum::Cancelado])
                            ->get();

                        if ($proyectosPendientes->isEmpty()) {
                            return null;
                        }

                        // Construimos el HTML para cada proyecto pendiente
                        $htmlParts = $proyectosPendientes->map(function ($proyecto) {
                            $url = ProyectoResource::getUrl('view', ['record' => $proyecto]);
                            $estado = $proyecto->estado;
                            $color = match ($estado) {
                                ProyectoEstadoEnum::Pendiente => 'warning',
                                ProyectoEstadoEnum::EnProgreso => 'primary',
                                default => 'gray',
                            };
                            $badgeHtml = "<span class='fi-badge fi-color-{$color}'>{$estado->getLabel()}</span>";
                            $icon = Blade::render("<x-heroicon-o-eye class='h-4 w-4' />");

                            return "<div class='flex items-center justify-between w-full'>
                                        <a href='{$url}' class='text-primary-600 hover:underline flex items-center space-x-1.5' target='_blank'>
                                            {$icon} <span>" . e($proyecto->nombre) . "</span>
                                        </a>
                                        {$badgeHtml}
                                    </div>";
                        });

                        return $htmlParts->implode('<br>');
                    }),

                Tables\Columns\TextColumn::make('precio_acordado')->label('Precio')->money('eur'),
                Tables\Columns\TextColumn::make('fecha_inicio')->label('Fecha de Inicio')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('fecha_fin')->label('Fecha de Fin')->date('d/m/Y'),
            ])
            ->actions([
                // Aquí podríamos añadir un botón para ver la Venta de origen
            ]);
    }
}