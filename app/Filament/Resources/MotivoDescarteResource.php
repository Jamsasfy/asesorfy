<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MotivoDescarteResource\Pages;
use App\Filament\Resources\MotivoDescarteResource\RelationManagers;
use App\Models\MotivoDescarte;
use Filament\Forms;
use Filament\Forms\Components\Section; // Para agrupar
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn; // Para el toggle en tabla
use Filament\Tables\Filters\TernaryFilter; // Para filtrar por activo/inactivo/todos
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MotivoDescarteResource extends Resource
{
    protected static ?string $model = MotivoDescarte::class;

    protected static ?string $navigationIcon = 'motivo-descarte-lead'; // Icono sugerido
    protected static ?string $navigationGroup = 'Gestión LEADS'; // Agrupar con otros ajustes
    protected static ?string $modelLabel = 'Motivo de Descarte';
    protected static ?string $pluralModelLabel = 'Motivos de Descarte de un Leads';
    protected static ?string $navigationLabel = 'Motivos de Descarte';
    protected static ?int $navigationSort = 1; // Orden en el menú (ajustar según necesites)





    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Motivo')
                    ->columns(1) // Una columna para este layout simple
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Motivo')
                            ->required()
                            ->maxLength(255)
                            // Validación de unicidad (ignorando el registro actual al editar)
                            ->unique(MotivoDescarte::class, 'nombre', ignoreRecord: true),

                        Textarea::make('descripcion')
                            ->label('Descripción (Opcional)')
                            ->rows(3), // Ajusta las filas según necesites

                        Toggle::make('activo')
                            ->label('Motivo Activo')
                            ->helperText('Desactiva para ocultarlo en nuevas selecciones, pero mantiene el histórico.')
                            ->required()
                            ->default(true), // Por defecto está activo
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre del Motivo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(50) // Limitar longitud en tabla para que no ocupe mucho
                    ->tooltip(fn (MotivoDescarte $record): ?string => $record->descripcion) // Mostrar completo al pasar el ratón
                    ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto

                // IconColumn::make('activo') // Opción 1: Mostrar como icono
                //     ->boolean()
                //     ->label('Activo'),

                ToggleColumn::make('activo') // Opción 2: Toggle para cambiar rápido
                    ->label('Activo')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto
            ])
            ->filters([
                TernaryFilter::make('activo') // Filtro Activo / Inactivo / Todos
                    ->label('Estado Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make() // Acción de borrado estándar
                 // Consideración: Si borras un motivo usado por leads, el campo en leads se pondrá a NULL
                 // (por el nullOnDelete). Podrías querer desactivarlo ('activo'=false) en lugar de borrarlo.
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
            // No hay relaciones definidas aquí normalmente
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMotivoDescartes::route('/'),
            'create' => Pages\CreateMotivoDescarte::route('/create'),
            'edit' => Pages\EditMotivoDescarte::route('/{record}/edit'),
        ];
    }
}