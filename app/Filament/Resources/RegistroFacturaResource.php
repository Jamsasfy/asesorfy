<?php

namespace App\Filament\Resources;

use App\Enums\EstadoRegistroFactura;
use App\Enums\TipoRegistroFactura;
use App\Filament\Resources\RegistroFacturaResource\Pages;
use App\Filament\Resources\RegistroFacturaResource\RelationManagers;
use App\Models\RegistroFactura;
use App\Models\Tercero;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Storage;





class RegistroFacturaResource extends Resource
{
    protected static ?string $model = RegistroFactura::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            // SECCIÓN 1: TIPO DE REGISTRO (Siempre visible)
            Section::make()
                ->schema([
                    Forms\Components\Radio::make('tipo')
                        ->label('Tipo de Registro')
                        ->options([
                            'emitida' => 'Emitida (Ingreso)',
                            'recibida' => 'Recibida (Gasto)',
                        ])
                        ->required()
                        ->live()
                        // La clave: En 'Crear', por defecto es 'recibida' y no se puede cambiar.
                        ->default('recibida')
                        ->disabled(fn ($livewire): bool => $livewire instanceof Pages\CreateRegistroFactura),
                ]),

            // SECCIÓN 2: FORMULARIO PARA FACTURAS RECIBIDAS
            Section::make('Detalle de Factura Recibida (Gasto)')
                ->visible(fn (Forms\Get $get): bool => $get('tipo') === 'recibida')
                ->schema([
                    Grid::make(3)->schema([
                        // --- COLUMNA IZQUIERDA: VISOR DEL JUSTIFICANTE ---
                        Group::make()
                            ->columnSpan(1)
                            ->schema([
                                FileUpload::make('justificante_path')
                                    ->label('Subir justificante')
                                    ->disk('public')
                                    ->directory('justificantes_facturas')
                                    ->required()
                                    ->visibleOn('create'),
                                
                                Placeholder::make('visor_justificante')
                                    ->label('Justificante Adjunto')
                                    ->content(function ($record): ?string {
                                        if ($record && $record->justificante_path && Storage::disk('public')->exists($record->justificante_path)) {
                                            $url = Storage::url($record->justificante_path);
                                            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer"><img src="' . $url . '" alt="Justificante" style="max-height: 200px; border-radius: 5px;"></a>';
                                        }
                                        return 'No hay justificante adjunto.';
                                    })
                                    ->visibleOn('edit'),
                            ]),

                        // --- COLUMNA DERECHA: FORMULARIO DE DATOS ---
                        Group::make()
                            ->columnSpan(2)
                            ->schema([
                                // Aquí dentro irán todos los campos de datos:
                                // Sección del Tercero, Sección de Fechas, Sección de Totales, etc.
                            ]),
                    ]),
                ]),

            // SECCIÓN 3: FORMULARIO PARA FACTURAS EMITIDAS (Solo visible en 'Edit')
            Section::make('Detalle de Factura Emitida (Ingreso)')
                ->visible(fn (Forms\Get $get): bool => $get('tipo') === 'emitida')
                ->schema([
                    // Aquí dentro irá nuestro Repeater para las líneas de factura
                ]),
        ]);
}

    public static function table(Table $table): Table
    {
       return $table
        ->columns([
            // Columna para el cliente, permite buscar por nombre y ordenar
            Tables\Columns\TextColumn::make('cliente.razon_social')
                ->searchable()
                ->sortable(),

            // Número de factura con búsqueda
            Tables\Columns\TextColumn::make('numero_factura')
                ->searchable(),

            // El tipo de registro (Emitida/Recibida) con un badge de color para diferenciar
         Tables\Columns\TextColumn::make('tipo')
                ->badge()
                ->color(fn (TipoRegistroFactura $state): string => match ($state) {
                    TipoRegistroFactura::EMITIDA => 'success',
                    TipoRegistroFactura::RECIBIDA => 'warning',
                })
                ->searchable(),

            // Nombre del tercero (proveedor/cliente final)
            Tables\Columns\TextColumn::make('tercero_nombre')
                ->label('Tercero')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto para no saturar

            // Total de la factura con formato de moneda y ordenable
            Tables\Columns\TextColumn::make('total_factura')
                ->money('EUR')
                ->sortable(),

            // Fecha con formato y ordenable
            Tables\Columns\TextColumn::make('fecha_expedicion')
                ->date()
                ->sortable(),
                
            // Columnas autocalculadas, también ordenables
            Tables\Columns\TextColumn::make('ejercicio')
                ->sortable(),
            Tables\Columns\TextColumn::make('trimestre')
                ->sortable(),
        ])
        ->filters([
            // Filtro para ver solo emitidas o recibidas
            Tables\Filters\SelectFilter::make('tipo')
                ->options([
                    'emitida' => 'Emitida (Ingreso)',
                    'recibida' => 'Recibida (Gasto)',
                ]),

            // Filtro para el Ejercicio (Año)
            Tables\Filters\SelectFilter::make('ejercicio')
                ->options(
                    // Obtenemos los años únicos de la tabla para no mostrar años vacíos
                    \App\Models\RegistroFactura::query()->select('ejercicio')->distinct()->pluck('ejercicio', 'ejercicio')->all()
                ),

            // Filtro para los Trimestres
            Tables\Filters\SelectFilter::make('trimestre')
                ->options([
                    '1T' => '1er Trimestre',
                    '2T' => '2º Trimestre',
                    '3T' => '3er Trimestre',
                    '4T' => '4º Trimestre',
                ]),
            // Filtro para buscar por cliente desde un desplegable
            Tables\Filters\SelectFilter::make('cliente')
                ->relationship('cliente', 'nombre')
                ->searchable(),
        ], layout: FiltersLayout::AboveContent)
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListRegistroFacturas::route('/'),
            'create' => Pages\CreateRegistroFactura::route('/create'),
            'edit' => Pages\EditRegistroFactura::route('/{record}/edit'),
        ];
    }
}
