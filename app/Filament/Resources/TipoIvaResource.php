<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoIvaResource\Pages;
use App\Filament\Resources\TipoIvaResource\RelationManagers;
use App\Models\TipoIva;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class TipoIvaResource extends Resource
{
    protected static ?string $model = TipoIva::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
               TextInput::make('porcentaje')
            ->label('Porcentaje IVA')
            ->placeholder('Ejemplo: 15.25')
            ->helperText('Porcentaje de IVA aplicable a las facturas. Solo introducir el valor separado por punto.')
               
                 ->suffix('%')
            //->numeric()
            ->required()
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01),

        TextInput::make('recargo_equivalencia')
            ->label('Recargo equivalencia')
            //->numeric()
             ->placeholder('Ejemplo: 15.25')
            ->helperText('Porcentaje de IVA de recargo de equivalencia aplicable a las facturas. Solo introducir el valor separado por punto.')
            ->default(0)   
            ->suffix('%')
            ->required(false)
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01),

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
                 TextColumn::make('porcentaje')->label('Porcentaje IVA')
                    
                 ->sortable(),

                TextColumn::make('recargo_equivalencia')->label('Recargo equivalencia')->sortable(),
                TextColumn::make('descripcion')->label('Descripción')->sortable(),
                IconColumn::make('activo')->label('Activo')->boolean()->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListTipoIvas::route('/'),
            'create' => Pages\CreateTipoIva::route('/create'),
            'edit' => Pages\EditTipoIva::route('/{record}/edit'),
        ];
    }
}
