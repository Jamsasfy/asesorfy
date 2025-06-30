<?php

namespace App\Filament\Resources;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Filament\Resources\ClienteSuscripcionResource\Pages;
use App\Filament\Resources\ClienteSuscripcionResource\RelationManagers;
use App\Models\ClienteSuscripcion;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class ClienteSuscripcionResource extends Resource
{
    protected static ?string $model = ClienteSuscripcion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

     public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cliente_id')
                ->relationship('cliente', 'razon_social')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('servicio_id')
                ->relationship('servicio', 'nombre')
                ->preload()
                ->searchable()
                ->required(),

            Select::make('estado')
                ->options(collect(ClienteSuscripcionEstadoEnum::cases())
                    ->mapWithKeys(fn ($estado) => [$estado->value => $estado->name]))
                ->required(),

            DatePicker::make('fecha_inicio')->required(),
            DatePicker::make('fecha_fin'),

            TextInput::make('precio_acordado')->numeric()->prefix('€')->required(),
            TextInput::make('cantidad')->numeric()->default(1),
            TextInput::make('ciclo_facturacion'),

            DatePicker::make('proxima_fecha_facturacion'),

            TextInput::make('descuento_tipo'),
            TextInput::make('descuento_valor')->numeric(),
            TextInput::make('descuento_descripcion'),
            DatePicker::make('descuento_valido_hasta'),

            TextInput::make('stripe_subscription_id'),
            Textarea::make('observaciones'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('cliente.razon_social')->searchable(),
            Tables\Columns\TextColumn::make('servicio.nombre')->searchable(),
            Tables\Columns\TextColumn::make('estado')->badge()
             ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
        ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
        ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
        ClienteSuscripcionEstadoEnum::CANCELADA => 'danger',
        // Puedes añadir más casos si tienes otros estados
        default => 'gray',
    }),
            Tables\Columns\TextColumn::make('fecha_inicio')->date('d/m/Y'),
            Tables\Columns\TextColumn::make('fecha_fin')->date('d/m/Y'),
            Tables\Columns\TextColumn::make('precio_acordado')->money('EUR'),
            Tables\Columns\TextColumn::make('ciclo_facturacion'),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime('d/m/Y H:i')
                ->label('Creado'),
            Tables\Columns\TextColumn::make('updated_at')
                ->dateTime('d/m/Y H:i')
                ->label('Creado'),
        ])
        ->filters([
                // Filtro por estado usando el Enum directamente
                Tables\Filters\SelectFilter::make('estado')
                    ->options(ClienteSuscripcionEstadoEnum::class), // Filament v3 lo convierte a opciones automáticamente

                // Filtro para buscar por cliente
                Tables\Filters\SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'razon_social')
                    ->searchable()
                    ->preload(),

                // Filtro para buscar por servicio
                Tables\Filters\SelectFilter::make('servicio_id')
                    ->label('Servicio')
                    ->relationship('servicio', 'nombre')
                    ->searchable()
                    ->preload(),
                
                // Filtro para saber si es tarifa principal
                Tables\Filters\TernaryFilter::make('es_tarifa_principal')
                    ->label('Es Tarifa Principal'),

            // ▼▼▼ EL NUEVO FILTRO PARA FACTURACIÓN ▼▼▼
                Tables\Filters\Filter::make('listos_para_facturar')
                    ->label('Listos para Facturar (Recurrentes Activos)')
                    ->query(function (Builder $query): Builder {
                        return $query
                            // 1. Solo estado ACTIVA
                            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA)
                            // 2. Solo servicios de tipo RECURRENTE
                            ->whereHas('servicio', function (Builder $q) {
                                $q->where('tipo', \App\Enums\ServicioTipoEnum::RECURRENTE);
                            })
                            // 3. Que ya hayan empezado
                            ->where('fecha_inicio', '<=', now())
                            // 4. Y que no hayan finalizado
                            ->where(function (Builder $q) {
                                $q->whereNull('fecha_fin')
                                  ->orWhere('fecha_fin', '>=', now());
                            });
                    })
                    ->toggle(), // Es un simple interruptor de Sí/No
           
                Tables\Filters\Filter::make('filtros_combinados')
                ->label('Filtros Avanzados')
                ->form([
                    Grid::make(4) // <-- Cambiamos la rejilla a 4 columnas
                        ->schema([
                            Select::make('year')
                                ->label('Año')
                                ->options(fn () => \App\Models\ClienteSuscripcion::query()->selectRaw('YEAR(fecha_inicio) as year')->whereNotNull('fecha_inicio')->distinct()->orderBy('year', 'desc')->pluck('year', 'year')->toArray()),
                            
                            Select::make('month')
                                ->label('Mes')
                                ->options([
                                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                                ]),
                            
                            Select::make('estado')
                                ->label('Estado')
                                ->options(ClienteSuscripcionEstadoEnum::class),
                            
                            // ▼▼▼ EL NUEVO FILTRO DE TIPO DE SERVICIO ▼▼▼
                            Select::make('tipo_servicio')
                                ->label('Tipo de Servicio')
                                ->options(ServicioTipoEnum::class),
                        ])
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['year'],
                            fn (Builder $query, $year): Builder => $query->whereYear('fecha_inicio', $year)
                        )
                        ->when(
                            $data['month'],
                            fn (Builder $query, $month): Builder => $query->whereMonth('fecha_inicio', $month)
                        )
                        ->when(
                            $data['estado'],
                            fn (Builder $query, $estado): Builder => $query->where('estado', $estado)
                        )
                        // ▼▼▼ Lógica para el nuevo filtro ▼▼▼
                        ->when(
                            $data['tipo_servicio'],
                            fn (Builder $query, $tipo): Builder => $query->whereHas('servicio', function (Builder $q) use ($tipo) {
                                $q->where('tipo', $tipo);
                            })
                        );
                })
                ->columnSpan(2),
   
            ], layout: Tables\Enums\FiltersLayout::AboveContent) // <-- Coloca los filtros arriba de la tabla
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClienteSuscripcions::route('/'),
            'create' => Pages\CreateClienteSuscripcion::route('/create'),
            'edit' => Pages\EditClienteSuscripcion::route('/{record}/edit'),
        ];
    }
}
