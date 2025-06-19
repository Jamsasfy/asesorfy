<?php

namespace App\Filament\Resources\ProyectoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Models\Documento;
use App\Models\DocumentoCategoria;
use App\Models\DocumentoSubtipo;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentosPolimorficos';
        protected static ?string $title = 'Documentos de este proyecto';

public function isReadOnly(): bool
{
    return false;
}

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->relationship('tipo', 'nombre')
                ->required()
                ->live(),
            Select::make('subtipo_documento_id')
                ->label('Subtipo')
                ->options(fn (callable $get) => DocumentoSubtipo::where('documento_categoria_id', $get('tipo_documento_id'))->pluck('nombre', 'id'))
                ->reactive()
                ->required()
                ->searchable()
                ->placeholder('Selecciona primero el tipo'),
            FileUpload::make('ruta')
                ->label('Archivo')
                ->disk('public')
                ->directory('documentos')
                ->maxSize(32768)
                ->required()
                ->acceptedFileTypes([
                    'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->preserveFilenames(false)
                ->visibility('public')
                 ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $set('nombre', $state->getClientOriginalName());
                    }
                }),
            TextInput::make('nombre')
                ->label('Nombre del documento')
                ->required()
                ->maxLength(255)
                ->placeholder('Se rellenará automáticamente con el nombre del archivo'),
            Textarea::make('observaciones')
                ->label('Observaciones')
                ->columnSpanFull(),
        ]);
    }



    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Subido por')
                    ->formatStateUsing(fn ($record) => $record->user->full_name ?? $record->user->name ?? 'Usuario desconocido'),
                TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($record) => $record->tipo->color ?? 'gray'),
                TextColumn::make('subtipo.nombre')
                    ->label('Subtipo')
                    ->badge()
                    ->color('info'),
                TextColumn::make('ruta')
                    ->label('Archivo')
                    ->url(fn ($record) => Storage::url($record->ruta), true)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn ($record) => $record->nombre),
                TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(30),
                IconColumn::make('verificado')
                    ->label('Verificado')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->action(function ($record, $livewire) {
                        if (!auth()->user()->can('verificado_documento')) {
                            Notification::make()
                                ->title('No tienes permiso para verificar este documento.')
                                ->danger()
                                ->send();
                            return;
                        }
                        $record->verificado = !$record->verificado;
                        $record->save();
                        Notification::make()
                            ->title($record->verificado ? 'Documento verificado' : 'Verificación retirada')
                            ->success()
                            ->send();
                    })
                    ->tooltip(fn ($record) => $record->verificado ? 'Marcar como NO verificado' : 'Marcar como verificado'),
                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
    \Filament\Tables\Actions\CreateAction::make()
        ->mutateFormDataUsing(function (array $data, $livewire) {
            $user = \Illuminate\Support\Facades\Auth::user();
            // ¡Así accedes al proyecto desde el RelationManager!
    $proyecto = $livewire->getOwnerRecord();
            $data['user_id'] = $user->id;
            $data['mime_type'] = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($data['ruta']);
            $data['verificado'] = $user->trabajador ? true : false;
            $data['cliente_id'] = $proyecto->cliente_id;  // <-- Esta línea soluciona el error
            // Puedes añadir aquí más lógica si quieres, por ejemplo, setear cliente_id si lo necesitas

            return $data;
        })
        ->after(function ($record, $livewire) {
            // Añadir comentario automático al proyecto cuando se sube un documento
            $proyecto = $record->documentable;
            $usuario = auth()->user()?->name ?? 'Usuario desconocido';

            if ($proyecto instanceof \App\Models\Proyecto) {
                $proyecto->comentarios()->create([
                    'user_id' => auth()->id(),
                    'contenido' => "📎 Se ha subido un documento {$record->nombre} por {$usuario}.",

                ]);
            }
            $livewire->redirect(request()->header('Referer') ?? url()->previous());
        }),
])
           
            ->filters([
                // Añade aquí los filtros que necesites
            ])
          
            ->actions([
               \Filament\Tables\Actions\ViewAction::make()
               ->visible(fn () => true)
                ->url(fn($record) => route('filament.admin.resources.documentos.view', ['record' => $record]))
        ->openUrlInNewTab(), // O quita esto si lo quieres en la misma pestaña

            \Filament\Tables\Actions\EditAction::make()->visible(fn () => true),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
