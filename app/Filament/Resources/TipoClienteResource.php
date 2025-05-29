<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoClienteResource\Pages;
use App\Filament\Resources\TipoClienteResource\RelationManagers;
use App\Models\TipoCliente;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TipoClienteResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TipoCliente::class;

    protected static ?string $navigationIcon = 'icon-group-by-ref-type';
    protected static ?string $navigationGroup = 'Configuración plataforma';

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
                Section::make('Información del tipo de cliente')->schema([
                    TextInput::make('nombre')
                        ->label('Tipo de cliente')
                        ->required()
                        ->maxLength(191)
                        ->unique(ignoreRecord: true),        
                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(3)
                        ->maxLength(1000),        
                    Toggle::make('activo')
                        ->label('¿Activo?')
                        ->default(true),   
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('nombre')
                ->label('Tipo de cliente')
                ->sortable()
                ->searchable(),

            TextColumn::make('descripcion')
                ->label('Descripción')
                ->limit(50)
                ->wrap()
                ->toggleable(),

            IconColumn::make('activo')
                ->label('Activo')
                ->boolean(),
        ])
        ->defaultSort('nombre')
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
            'index' => Pages\ListTipoClientes::route('/'),
            'create' => Pages\CreateTipoCliente::route('/create'),
            'edit' => Pages\EditTipoCliente::route('/{record}/edit'),
        ];
    }
}
