<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OficinaResource\Pages;
use App\Filament\Resources\OficinaResource\RelationManagers;
use App\Models\Oficina;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OficinaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Oficina::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Configuración plataforma';
    protected static ?string $navigationLabel = 'Oficinas';
    protected static ?string $modelLabel = 'Oficina';
    protected static ?string $pluralModelLabel = 'Oficinas';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(191),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha creación')
                        ->dateTime('d/m/y - H:m')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListOficinas::route('/'),
            'create' => Pages\CreateOficina::route('/create'),
            'edit' => Pages\EditOficina::route('/{record}/edit'),
        ];
    }
}
