<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentoCategoriaResource\Pages;
use App\Filament\Resources\DocumentoCategoriaResource\RelationManagers;
use App\Models\DocumentoCategoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;





class DocumentoCategoriaResource extends Resource implements HasShieldPermissions 
{
    protected static ?string $model = DocumentoCategoria::class;

    protected static ?string $navigationIcon = 'icon-tipodocumento';

    protected static ?string $navigationGroup = 'Configuración plataforma';
    protected static ?string $navigationLabel = 'Tipo general documento';
    protected static ?string $modelLabel = 'Tipo general documento';
    protected static ?string $pluralModelLabel = 'Tipos general de documentos';

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
                ->label('Nombre de la categoría')
                ->required()
                ->maxLength(100)
                ->placeholder('Ej: Fiscal, Contable, General')
                ->helperText('Define una categoría general para agrupar tipos de documentos.'),
                Select::make('color')
                ->label('Color del badge')
                ->options([
                    'primary' => 'Azul (Primary)',
                    'success' => 'Verde (Success)',
                    'warning' => 'Amarillo (Warning)',
                    'danger' => 'Rojo (Danger)',
                    'info' => 'Celeste (Info)',
                    'gray' => 'Gris (Gray)',
                ])
                ->default('gray')
                ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('nombre')
                ->label('Nombre')
                ->badge()
                ->color(fn ($record) => $record->color ?? 'gray')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Creado')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
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
            'index' => Pages\ListDocumentoCategorias::route('/'),
            'create' => Pages\CreateDocumentoCategoria::route('/create'),
            'edit' => Pages\EditDocumentoCategoria::route('/{record}/edit'),
        ];
    }
}
