<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComentariosRelationManager extends RelationManager
{
    protected static string $relationship = 'Comentarios';


 // QUITA el “static” y asegúrate de no ponerle parámetros
 public function isReadOnly(): bool
 {
     return false;
 }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('contenido')
                    ->label('Comentario')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contenido')
            ->columns([
                ViewColumn::make('contenido')
                ->label('Comentario')
                ->view('filament.components.comentario-card')
                ->grow(false),
               
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                
            ])
           
            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();
                    return $data;
                }),
            ])
            ->actions([                
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\EditAction::make(),

            ])
            ->bulkActions([
                /* Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]), */
            ]);
    }
}
