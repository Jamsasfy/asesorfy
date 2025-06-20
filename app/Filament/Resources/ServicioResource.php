<?php

namespace App\Filament\Resources;

use App\Enums\CicloFacturacionEnum;
use App\Enums\ServicioTipoEnum; // Importar Enum
use App\Filament\Resources\ServicioResource\Pages;
use App\Filament\Resources\ServicioResource\RelationManagers;
use App\Models\Servicio;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle; // Importar Toggle
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn; // Importar IconColumn
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn; // Importar ToggleColumn
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter; // Importar SelectFilter
use Filament\Tables\Filters\TernaryFilter; // Importar TernaryFilter
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static ?string $navigationIcon = 'icon-servicios'; // O el icono que prefieras
    protected static ?string $navigationGroup = 'Gestión VENTAS'; // O donde quieras agruparlo
    protected static ?string $modelLabel = 'Servicio';
    protected static ?string $pluralModelLabel = 'Servicios que ofrecemos';
    protected static ?int $navigationSort = 1; // Orden en el menú

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Detalles del Servicio')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),

                        Select::make('tipo')
                            ->required()
                            ->reactive()
                            ->options(ServicioTipoEnum::class),

                        TextInput::make('precio_base')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->minValue(0),

                        Select::make('ciclo_facturacion')
                            ->label('Ciclo de facturación por defecto')
                            ->options(
                                collect(CicloFacturacionEnum::cases())
                                    ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                    ->toArray()
                            )
                            ->default(CicloFacturacionEnum::MENSUAL->value)
                            ->nullable()
                            ->searchable()
                            ->visible(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value)
                            ->required(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value),
                    ]),

                    Grid::make(3)->schema([
                        Toggle::make('activo')
                            ->required()
                            ->default(true)
                            ->label('Servicio Activo'),

                        Toggle::make('es_tarifa_principal')
                            ->required()
                            ->default(true)
                            ->label('¿Es tarifa principal?')
                            ->helperText('Se usará como tarifa por defecto para este servicio.'),

                        Toggle::make('requiere_proyecto_activacion')
                            ->label('Requiere Proyecto de Activación')
                            ->helperText('Ej. alta de autónomo, capitalización, etc.'),
                    ]),

                    Textarea::make('descripcion')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),
        ]);
}
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->badge() // Mostrar el tipo como badge
                    ->formatStateUsing(fn (ServicioTipoEnum $state): string => $state->getLabel()) // Usar etiqueta legible
                    ->color(fn (ServicioTipoEnum $state): string => match ($state) {
                        ServicioTipoEnum::UNICO => 'info',
                        ServicioTipoEnum::RECURRENTE => 'success',
                    }) // Colores para los badges
                    ->sortable(),
                    
                     TextColumn::make('ciclo_facturacion')
                    ->label('Ciclo de facturación')
                    ->formatStateUsing(fn (CicloFacturacionEnum $state): string => $state->label()) // Usar etiqueta del Enum
                    ->color(fn (CicloFacturacionEnum $state): string => match ($state) {
                        CicloFacturacionEnum::MENSUAL => 'primary',
                        CicloFacturacionEnum::TRIMESTRAL => 'secondary',
                        CicloFacturacionEnum::ANUAL => 'warning',
                    }) // Colores para los badges
                   
                    ->searchable()
                    ->sortable(),
                TextColumn::make('precio_base')
                    ->money('EUR') // Formatear como moneda
                    ->sortable(),
                IconColumn::make('activo') // Columna de icono para booleano
                    ->boolean()
                    ->sortable(),
                IconColumn::make('es_tarifa_principal') // Columna de icono para booleano
                ->label('¿Es tarifa principal?')
                    ->boolean()
                    ->sortable(),    
//requiere_proyecto_activacion
            IconColumn::make('requiere_proyecto_activacion') // Columna de icono para booleano
                ->label('Proyecto asiociado')
                    ->boolean()
                    ->sortable(), 

                // Opcional: ToggleColumn para cambiar activo/inactivo desde la tabla
                // ToggleColumn::make('activo'),
                TextColumn::make('created_at')
                ->label('Servicio creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Actualizado servicio')
                    ->sortable(),
                   
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->options(collect(ServicioTipoEnum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                TernaryFilter::make('activo') // Filtro Sí/No/Todos para activos
                    ->label('Estado Activo'),
             ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(7)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Opcional: Podrías añadir DeleteAction si se pueden borrar
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Opcional: Podrías añadir DeleteBulkAction
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // No hay relaciones definidas aquí por ahora
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServicios::route('/'),
            'create' => Pages\CreateServicio::route('/create'),
          //  'view' => Pages\ViewServicio::route('/{record}'),
            'edit' => Pages\EditServicio::route('/{record}/edit'),
        ];
    }
}