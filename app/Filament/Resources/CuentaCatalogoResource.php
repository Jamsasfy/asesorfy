<?php

namespace App\Filament\Resources;

use App\Enums\TipoCuentaContable;
use App\Filament\Resources\CuentaCatalogoResource\Pages;
use App\Models\CuentaCatalogo;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;



class CuentaCatalogoResource extends Resource
{
    protected static ?string $model = CuentaCatalogo::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $label = 'Cuenta Plan General Base';
    protected static ?string $pluralLabel = 'Cuentas Plan general Base';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')
                ->label('Código PGC')
                ->required()
                ->maxLength(20),
            Forms\Components\TextInput::make('descripcion')
                ->label('Descripción')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('grupo')
                ->maxLength(10),
            Forms\Components\TextInput::make('subgrupo')
                ->maxLength(10),
            Forms\Components\TextInput::make('nivel')
                ->numeric()
                ->nullable(),
            Forms\Components\TextInput::make('origen')
                ->maxLength(20),
            Forms\Components\Select::make('tipo')
                    ->label('Tipo de cuenta')
                    ->options(
                        collect(\App\Enums\TipoCuentaContable::cases())
                            ->mapWithKeys(fn($enum) => [$enum->value => $enum->label()])
                            ->toArray()
                    )
                    ->required(),
            Forms\Components\Toggle::make('es_activa')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('tipoCuenta')
                ->label('Tipo Cuenta')
                ->getStateUsing(fn($record) => substr($record->codigo, 0, 4))
                ->sortable(),

            Tables\Columns\TextColumn::make('prefijo')
                ->label('Prefijo')
                ->getStateUsing(fn($record) => substr($record->codigo, 4))
                ->sortable(),

            Tables\Columns\TextColumn::make('descripcion')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('tipo')
                ->label('Tipo')
                ->sortable(),

            Tables\Columns\IconColumn::make('es_activa')
                ->label('Activa')
                ->boolean(),
        ])
        ->filters([
            SelectFilter::make('tipo')
                ->label('Filtrar por tipo')
                ->options(
                    collect(TipoCuentaContable::cases())
                        ->mapWithKeys(fn($tipo) => [$tipo->value => $tipo->label()])
                        ->toArray()
                )
                ->multiple(),
                  Filter::make('es_activa')
    ->label('Sólo activos')
    ->query(fn ($query) => $query->where('es_activa', true))
    ->toggle(),
        ], layout: FiltersLayout::AboveContent)
        ->defaultSort('codigo');
       
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuentaCatalogos::route('/'),
            'create' => Pages\CreateCuentaCatalogo::route('/create'),
            'edit' => Pages\EditCuentaCatalogo::route('/{record}/edit'),
        ];
    }
}
