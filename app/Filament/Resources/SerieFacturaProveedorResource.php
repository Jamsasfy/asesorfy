<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerieFacturaProveedorResource\Pages;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\SerieFacturaProveedor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;

class SerieFacturaProveedorResource extends Resource
{
    protected static ?string $model = SerieFacturaProveedor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cliente_id')
            ->label('Cliente')
            ->options(Cliente::pluck('razon_social', 'id'))
            ->searchable()
            ->required(),

        Select::make('proveedor_id')
            ->label('Proveedor')
            ->options(function ($get) {
                $clienteId = $get('cliente_id');
                return $clienteId
                    ? Proveedor::where('cliente_id', $clienteId)->pluck('nombre', 'id')
                    : [];
            })
            ->searchable()
            ->required(),

        TextInput::make('codigo')
            ->label('Código de Serie')
            ->required()
            ->maxLength(20),

        TextInput::make('descripcion')
            ->label('Descripción'),

        Toggle::make('activo')
            ->label('Activo')
            ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                    TextColumn::make('cliente.razon_social')->label('Cliente')->sortable()->searchable(),
                    TextColumn::make('proveedor.nombre')->label('Proveedor')->sortable()->searchable(),
                    TextColumn::make('codigo')->label('Código de Serie')->sortable()->searchable(),
                    TextColumn::make('descripcion')->label('Descripción')->sortable()->searchable(),
                    IconColumn::make('activo')->label('Activo')->boolean()->sortable(),
            ])
            ->filters([
                SelectFilter::make('cliente_id')
                ->label('Cliente')
                ->options(Cliente::pluck('razon_social', 'id')->toArray()),

            SelectFilter::make('proveedor_id')
                ->label('Proveedor')
                ->options(Proveedor::pluck('nombre', 'id')->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSerieFacturaProveedors::route('/'),
            'create' => Pages\CreateSerieFacturaProveedor::route('/create'),
            'edit' => Pages\EditSerieFacturaProveedor::route('/{record}/edit'),
        ];
    }
}
