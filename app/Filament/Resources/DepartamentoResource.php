<?php

namespace App\Filament\Resources;



use App\Filament\Resources\DepartamentoResource\Pages;
use App\Filament\Resources\DepartamentoResource\RelationManagers;
use App\Models\Departamento;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;



class DepartamentoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Departamento::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Configuración plataforma';
    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $modelLabel = 'Departamento';
    protected static ?string $pluralModelLabel = 'Departamentos';


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
                 Select::make('coordinador_id')
                ->label('Coordinador Asignado')
                ->relationship(
                    name: 'coordinador',
                    titleAttribute: 'name',
                    // Mostramos solo usuarios con el rol 'coordinador'
                    modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                        'roles', fn ($q) => $q->where('name', 'coordinador')
                    )
                )
                ->searchable()
                ->preload()
                ->nullable(), // Un departamento puede no tener coordinador asignado    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),

                     TextColumn::make('coordinador.name')
                ->label('Coordinador Asignado')
                ->badge()
                ->color('success')
                ->placeholder('Sin asignar') // Texto si no hay coordinador
                ->sortable(),
            
            // Columna extra muy útil: cuenta cuántos trabajadores tiene el depto.
           TextColumn::make('trabajadores_count')
                ->label('Nº de Trabajadores')
                // Usamos state() para calcular el valor manualmente
                ->state(function (Departamento $record): int {
                    return $record->trabajadores()->count();
                })
                ->sortable(),
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
            'index' => Pages\ListDepartamentos::route('/'),
            'create' => Pages\CreateDepartamento::route('/create'),
            'edit' => Pages\EditDepartamento::route('/{record}/edit'),
        ];
    }
}
