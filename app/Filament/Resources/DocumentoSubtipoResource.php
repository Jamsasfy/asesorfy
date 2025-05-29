<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentoSubtipoResource\Pages;
use App\Filament\Resources\DocumentoSubtipoResource\RelationManagers;
use App\Models\DocumentoSubtipo;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DocumentoSubtipoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = DocumentoSubtipo::class;

    protected static ?string $navigationIcon = 'icon-subtipodocumento';

    protected static ?string $navigationGroup = 'Configuración plataforma';
    protected static ?string $navigationLabel = 'Subtipo documento';
    protected static ?string $modelLabel = 'Subtipo documento';
    protected static ?string $pluralModelLabel = 'Subtipos de documentos';



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
                Select::make('documento_categoria_id')
                ->label('Categoría')
                ->relationship('categoria', 'nombre')
                ->required()
              
                ->preload()
                ->searchable(),

            TextInput::make('nombre')
                ->label('Nombre del subtipo')
                ->required()
                ->maxLength(150)
               
                ->placeholder('Ej: Modelo 303, Declaración trimestral, Nóminas...'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->categoria?->color ?? 'gray'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('documento_categoria_id')
                    ->label('Filtrar por categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable(),
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
            'index' => Pages\ListDocumentoSubtipos::route('/'),
            'create' => Pages\CreateDocumentoSubtipo::route('/create'),
            'edit' => Pages\EditDocumentoSubtipo::route('/{record}/edit'),
        ];
    }
}
