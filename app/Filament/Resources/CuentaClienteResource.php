<?php

namespace App\Filament\Resources;

use App\Enums\TipoCuentaContable;
use App\Filament\Resources\CuentaClienteResource\Pages;
use App\Models\CuentaCliente;
use App\Models\Cliente;
use App\Models\CuentaCatalogo;

use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

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
                ->dehydrated()
                ->afterStateHydrated(function ($state, $set, $record) {
                    $set('codigo_prefijo', $record?->codigoPrefijo);
                })
                ->reactive(),

            TextInput::make('codigo_sufijo')
                ->label('Sufijo')
                ->disabled()
                ->dehydrated()
                ->afterStateHydrated(function ($state, $set, $record) {
                    $set('codigo_sufijo', $record?->codigoSufijo);
                })
                ->reactive(),

            TextInput::make('descripcion')
                ->label('Descripción')
                ->required(),
            Toggle::make('es_activa')
                ->label('Cuenta activa')
                ->default(true) // opcional: valor por defecto
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
                TextColumn::make('cuentaCatalogo') // accedemos al modelo relacionado
                ->label('Cuenta base')
                ->formatStateUsing(function ($state, $record) {
                    $codigo = $record->cuentaCatalogo?->codigo ? substr($record->cuentaCatalogo->codigo, 0, 4) : '';
                    $descripcion = $record->cuentaCatalogo?->descripcion ?? '';
                    return "{$codigo} - {$descripcion}";
                })
                ->sortable()
                ->searchable(),
                Tables\Columns\IconColumn::make('es_activa')->label('Activa')->boolean(),
                 
            ])
             ->actions([
           Tables\Actions\EditAction::make(),
            DeleteAction::make()
        ])
         ->filters([
            // Filtro por Cliente (select)
            SelectFilter::make('cliente_id')
                ->label('Cliente')
                ->options(Cliente::pluck('razon_social', 'id')->toArray())
                ->searchable(),

            // Filtro por Prefijo (text input)
            Filter::make('codigo_prefijo')
                ->label('Prefijo')
                ->form([
                    Forms\Components\TextInput::make('codigo_prefijo')->placeholder('Ejemplo: 6280'),
                ])
                ->query(function ($query, array $data) {
                    if (!empty($data['codigo_prefijo'])) {
                        $query->where('codigo', 'like', $data['codigo_prefijo'] . '%');
                    }
                }),

           
            // Filtro toggle para Activa
            Filter::make('es_activa')
                ->label('Sólo activas')
                ->query(fn ($query) => $query->where('es_activa', true))
                ->toggle(),
        ], layout: FiltersLayout::AboveContent)
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
