<?php

namespace App\Filament\Resources\DepartamentoResource\RelationManagers;

use App\Filament\Resources\TrabajadorResource;
use App\Filament\Resources\UserResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use App\Models\Trabajador;
use Filament\Support\Enums\IconSize;
use Illuminate\Database\Eloquent\Builder;

class TrabajadoresRelationManager extends RelationManager
{
    protected static string $relationship = 'trabajadores';
    protected static ?string $title = 'Trabajadores en este Departamento';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nombre'),

                // ▼▼▼ COLUMNA DE ROLES AÑADIDA ▼▼▼
                Tables\Columns\TextColumn::make('user.roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email'),
                
                   
                Tables\Columns\TextColumn::make('cargo')
                    ->label('Cargo'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('vincularTrabajador')
                    ->label('Vincular Trabajador Existente')
                    ->icon('heroicon-o-plus')
                    // ▼▼▼ VISIBILIDAD CONDICIONAL AÑADIDA ▼▼▼
                    ->visible(fn (): bool => Trabajador::whereNull('departamento_id')->exists())
                    ->form([
                        Select::make('trabajador_id')
                            ->label('Trabajador a vincular')
                            ->options(
                                Trabajador::whereNull('departamento_id')->get()->pluck('user.full_name', 'id')
                            )
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        $trabajador = Trabajador::find($data['trabajador_id']);
                        $trabajador->departamento_id = $this->ownerRecord->id;
                        $trabajador->save();
                    })
            ])
            ->actions([
                 Tables\Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->label('Editar trabajador')
            
                ->url(fn (Trabajador $record): string => TrabajadorResource::getUrl('edit', ['record' => $record]))
                ->openUrlInNewTab(),
                Action::make('ver_usuario')
                ->label('Permisos y acceso app')
                ->icon('heroicon-o-users') // Puedes cambiar el ícono aquí
                ->iconSize(IconSize::Small)
                ->color('warning')
                ->tooltip('Permisos del usuario y contraseña de acceso')
                ->url(fn (Trabajador $record): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                ->openUrlInNewTab(), // Opcional, si quieres abrir en nueva pestaña
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}