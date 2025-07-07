<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FacturaResource\Pages;
use App\Models\Factura;
use App\Models\Servicio;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use App\Services\ConfiguracionService;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Action;
use Filament\Forms\Components\Actions;



class FacturaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Factura::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

     public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }



public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Cabecera de la Factura')->columns(4)->schema([
                Select::make('cliente_id')
                    ->relationship('cliente', 'razon_social')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2),

                TextInput::make('numero_factura')
                    ->required()
                    ->maxLength(255),

                Select::make('estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'pendiente_pago' => 'Pendiente de Pago',
                        'pagada' => 'Pagada',
                        'anulada' => 'Anulada',
                    ])
                    ->required()
                    ->default('borrador'),

                DatePicker::make('fecha_emision')
                    ->required()
                    ->default(now()),

                DatePicker::make('fecha_vencimiento')
                    ->required(),
            ]),

            Section::make('Líneas de la Factura')->schema([
                Repeater::make('items')
                    ->relationship()
                    ->live()
                    ->default([
                        ['cantidad' => 1, 'precio_unitario' => 0, 'porcentaje_iva' => 21],
                    ])
                    ->columns(12)
                    ->afterStateHydrated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set))
                     ->addAction(fn ($action) =>
                            $action
                                ->label('Añadir Línea o actualizar totales')
                                ->after(fn (Get $get, Set $set) => self::recalcularTotales($get, $set))
                        )
                            ->helperText('Añade todas las líneas de la factura antes de guardar. Si metes solo un item o servicio, debes pulsar en "Añadir Linea o actualizar totales" para que se actualice la factura en base de datos correctamente.')

                        ->deleteAction(fn ($action) =>
                            $action->after(fn (Get $get, Set $set) => self::recalcularTotales($get, $set))
                        )
                    ->schema([
                        Select::make('servicio_id')
                            ->label('Servicio')
                            ->searchable()
                            ->nullable()
                            ->options(Servicio::all()->pluck('nombre', 'id'))
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if (!$state) return;

                                $servicio = Servicio::find($state);
                                if ($servicio) {
                                    $set('descripcion', $servicio->nombre);
                                    $set('precio_unitario', $servicio->precio_base);
                                    $set('porcentaje_iva', 21);
                                }

                                self::recalcularTotales($get, $set);
                            })
                            ->columnSpan(4),

                        TextInput::make('descripcion')
                            ->required()
                            ->columnSpan(8),

                        TextInput::make('cantidad')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->columnSpan(4)
                            ->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set)),

                        TextInput::make('precio_unitario')
                            ->label('Precio Unit.')
                            ->numeric()
                            ->required()
                            ->columnSpan(4)
                            ->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set)),

                        TextInput::make('porcentaje_iva')
                            ->label('% IVA')
                            ->numeric()
                            ->required()
                            ->default(fn () => ConfiguracionService::get('IVA_general', 0.21) * 100)
                            ->columnSpan(4)
                            ->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalcularTotales($get, $set)),
                    ]),
            ]),

            Section::make('Totales')->columns(3)->schema([
                TextInput::make('base_imponible')
                    ->readOnly()
                    ->numeric()
                    ->prefix('€')
                    ->default(0),

                TextInput::make('total_iva')
                    ->label('Total IVA')
                    ->readOnly()
                    ->numeric()
                    ->prefix('€')
                    ->default(0),

                TextInput::make('total_factura')
                    ->label('TOTAL')
                    ->readOnly()
                    ->numeric()
                    ->prefix('€')
                    ->default(0),
            ]),
             Hidden::make('mostrar_guardar')->default(false),
        ]);
}



public static function mutateFormDataBeforeCreate(array $data): array
{
    $totales = self::calcularTotalesDesdeItems($data['items'] ?? []);
    return array_merge($data, $totales);
}

protected static function calcularTotalesDesdeItems(array $items): array
{
    $baseImponibleTotal = 0;
    $ivaTotalAcumulado = 0;

    foreach ($items as $item) {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $precioUnitario = (float)($item['precio_unitario'] ?? 0);
        $porcentajeIva = (float)($item['porcentaje_iva'] ?? 0);

        $subtotalItem = $cantidad * $precioUnitario;
        $ivaDelItem = $subtotalItem * ($porcentajeIva / 100);

        $baseImponibleTotal += $subtotalItem;
        $ivaTotalAcumulado += $ivaDelItem;
    }

    return [
        'base_imponible' => round($baseImponibleTotal, 2),
        'total_iva' => round($ivaTotalAcumulado, 2),
        'total_factura' => round($baseImponibleTotal + $ivaTotalAcumulado, 2),
    ];
}




public static function recalcularTotales(Get $get, Set $set): void
{
    $items = $get('items');
    if (!is_array($items)) {
        return;
    }

    $baseImponibleTotal = 0;
    $ivaTotalAcumulado = 0;

    foreach ($items as $item) {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $precioUnitario = (float)($item['precio_unitario'] ?? 0);
        $porcentajeIva = (float)($item['porcentaje_iva'] ?? 0); // Ej: 21, no 0.21

        $subtotalItem = $cantidad * $precioUnitario;
        $ivaDelItem = $subtotalItem * ($porcentajeIva / 100);

        $baseImponibleTotal += $subtotalItem;
        $ivaTotalAcumulado += $ivaDelItem;
    }

    $set('base_imponible', round($baseImponibleTotal, 2));
    $set('total_iva', round($ivaTotalAcumulado, 2));
    $set('total_factura', round($baseImponibleTotal + $ivaTotalAcumulado, 2));
}


    
    public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('numero_factura')->searchable()->sortable(),
            TextColumn::make('cliente.razon_social')->searchable()->sortable(),
            TextColumn::make('fecha_emision')->date('d/m/Y')->sortable(),
            TextColumn::make('total_factura')->money('EUR')->sortable(),
            TextColumn::make('estado')->badge()
                ->color(fn (string $state): string => match ($state) {
                    'borrador' => 'gray',
                    'pendiente_pago' => 'warning',
                    'pagada' => 'success',
                    'anulada' => 'danger',
                    default => 'gray',
                }),
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\ViewAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
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
            'index' => Pages\ListFacturas::route('/'),
            'create' => Pages\CreateFactura::route('/create'),
            'edit' => Pages\EditFactura::route('/{record}/edit'),
        ];
    }
}
