<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoServicioBaseResource\Pages;
use App\Models\ProductoServicioBase;
use App\Models\CuentaCatalogo;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ProductoServicioBaseResource extends Resource
{
    protected static ?string $model = ProductoServicioBase::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $label = 'Producto/Servicio Base';
    protected static ?string $pluralLabel = 'Productos/Servicios Base';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('descripcion')
                ->maxLength(255),
            Forms\Components\Select::make('cuenta_catalogo_id')
                ->label('Cuenta contable base')
                ->required()
                ->options(
                    CuentaCatalogo::orderBy('codigo')->pluck('descripcion', 'id')
                        ->map(function ($descripcion, $id) {
                            $codigo = CuentaCatalogo::find($id)?->codigo;
                            return "$codigo — $descripcion";
                        })->toArray()
                )
                ->searchable(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nombre'),
            Tables\Columns\TextColumn::make('cuentaCatalogo.codigo')
                ->label('Cuenta')
                ->sortable(),
            Tables\Columns\TextColumn::make('cuentaCatalogo.descripcion')
                ->label('Descripción cuenta'),
            Tables\Columns\TextColumn::make('descripcion')->wrap(),
        ])->filters([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductoServicioBases::route('/'),
            'create' => Pages\CreateProductoServicioBase::route('/create'),
            'edit' => Pages\EditProductoServicioBase::route('/{record}/edit'),
        ];
    }
}
