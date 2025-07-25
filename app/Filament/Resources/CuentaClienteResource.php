<?php

namespace App\Filament\Resources;

use App\Enums\TipoCuentaContable;
use App\Filament\Resources\CuentaClienteResource\Pages;
use App\Models\CuentaCliente;
use App\Models\Cliente;
use App\Models\CuentaCatalogo;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;

class CuentaClienteResource extends Resource
{
    protected static ?string $model = CuentaCliente::class;

    protected static ?string $navigationIcon = 'icon-serviciosproductosusuario';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $label = 'Cuenta Cliente Plan General';
    protected static ?string $pluralLabel = 'Cuentas Cliente Plan General';

public static function form(Forms\Form $form): Forms\Form
{
   $options = \App\Models\CuentaCatalogo::whereIn('tipo', [
        TipoCuentaContable::GASTO->value,
        TipoCuentaContable::OTRO->value,
    ])
    ->where('codigo', 'like', '%000000000')  // Solo cuentas raíz
    ->orderBy('codigo')
    ->get()
    ->mapWithKeys(function ($c) {
        $codigoFormateado = substr($c->codigo, 0, 4);
        return [$c->id => "{$codigoFormateado} {$c->descripcion}"];
    })
    ->toArray();

    return $form
        ->schema([
            Select::make('cliente_id')
                ->label('Cliente')
                ->options(\App\Models\Cliente::pluck('razon_social', 'id')->toArray())
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, $set) => $set('codigo_prefijo', null)),

           Select::make('cuenta_catalogo_id')
    ->label('Tipo de cuenta base (gasto y otro)')
    ->options($options)
    ->searchable()
    ->placeholder('Seleccione una opción')
    ->required()
    ->reactive()
    ->afterStateUpdated(function ($state, $set, $get) {
        $clienteId = $get('cliente_id');
        $cuentaCatalogoId = $state;

        if ($clienteId && $cuentaCatalogoId) {
            $cuentaCatalogo = \App\Models\CuentaCatalogo::find($cuentaCatalogoId);
            if ($cuentaCatalogo) {
                $codigoBaseCompleto = $cuentaCatalogo->codigo;

                $nuevoCodigo = \App\Models\CuentaCliente::generarSiguienteCodigoCombinado($codigoBaseCompleto, $clienteId);

                $prefijo = substr($nuevoCodigo, 0, 4);
                $sufijo = substr($nuevoCodigo, 4);

                $set('codigo_prefijo', $prefijo);
                $set('codigo_sufijo', $sufijo);
            }
        }
    }),

            TextInput::make('codigo_prefijo')
                ->label('Prefijo')
                ->disabled()
                ->reactive(),

            TextInput::make('codigo_sufijo')
                ->label('Sufijo')
                ->disabled()      // No editable
                ->reactive(),

            TextInput::make('descripcion')
                ->label('Descripción')
                ->required(),
        ]);
}

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cliente.razon_social')->label('Cliente'),
                Tables\Columns\TextColumn::make('codigo')->label('Código')->sortable(),
                Tables\Columns\TextColumn::make('descripcion')->label('Descripción')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('cuentaCatalogo.codigo')->label('Cuenta base'),
                Tables\Columns\IconColumn::make('es_activa')->label('Activa')->boolean(),
            ])
            ->defaultSort('codigo');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuentaClientes::route('/'),
            'create' => Pages\CreateCuentaCliente::route('/create'),
            'edit' => Pages\EditCuentaCliente::route('/{record}/edit'),
        ];
    }
}
