<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RetencionIrpfResource\Pages;
use App\Filament\Resources\RetencionIrpfResource\RelationManagers;
use App\Models\RetencionIrpf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class RetencionIrpfResource extends Resource
{
    protected static ?string $model = RetencionIrpf::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('porcentaje')
            ->label('Porcentaje IRPF')
           
            ->required()
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01)
             ->placeholder('Ejemplo: 15.25')
            ->helperText('Porcentaje de retención IRPF aplicable a las facturas. Solo introducir el valor separado por punto.')
               //->formatStateUsing(fn ($state) => "{$state}%")
                 ->suffix('%'),  // Aquí añades el símbolo %,

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
        TextColumn::make('porcentaje')->label('Porcentaje IRPF')->sortable(),
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
            'index' => Pages\ListRetencionIrpfs::route('/'),
            'create' => Pages\CreateRetencionIrpf::route('/create'),
            'edit' => Pages\EditRetencionIrpf::route('/{record}/edit'),
        ];
    }
}
