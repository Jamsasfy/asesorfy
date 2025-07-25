<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoServicioClienteResource\Pages;
use App\Models\ProductoServicioCliente;
use App\Models\CuentaCliente;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ProductoServicioClienteResource extends Resource
{
    protected static ?string $model = ProductoServicioCliente::class;

    protected static ?string $navigationIcon = 'icon-serviciosproductosusuario';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $label = 'Producto/Servicio Personalizado';
    protected static ?string $pluralLabel = 'Productos/Servicios Personalizados';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('cliente_id')
                ->label('Cliente AsesorFy')
                ->relationship('cliente', 'razon_social')
                ->required()
                ->preload()
                ->searchable(),

            Forms\Components\TextInput::make('nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('descripcion')
                ->maxLength(255),
            Forms\Components\Select::make('cuenta_cliente_id')
                ->label('Cuenta contable propia')
                ->relationship('cuentaCliente', 'descripcion', function ($query, $get) {
                    // Opcional: puedes filtrar por cliente si lo deseas
                    if ($get('cliente_id')) {
                        $query->where('cliente_id', $get('cliente_id'));
                    }
                })
                ->required()
                ->searchable(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('cliente.razon_social')->label('Cliente'),
            Tables\Columns\TextColumn::make('nombre'),
            Tables\Columns\TextColumn::make('cuentaCliente.codigo')->label('Cuenta'),
            Tables\Columns\TextColumn::make('cuentaCliente.descripcion')->label('Descripción cuenta'),
            Tables\Columns\TextColumn::make('descripcion')->wrap(),
        ])->filters([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductoServicioClientes::route('/'),
            'create' => Pages\CreateProductoServicioCliente::route('/create'),
            'edit' => Pages\EditProductoServicioCliente::route('/{record}/edit'),
        ];
    }
}

