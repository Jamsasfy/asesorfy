<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';
    protected static ?string $title = 'Documentos';

    // QUITA el “static” y asegúrate de no ponerle parámetros
    public function isReadOnly(): bool
    {
        return false;
    }


/* public function tableQuery()
{
    $cliente = $this->getOwnerRecord();
    $proyectosIds = $cliente->proyectos()->pluck('id');

    return \App\Models\Documento::query()
        ->where(function ($q) use ($cliente, $proyectosIds) {
            $q->where(function ($q2) use ($cliente) {
                $q2->where('documentable_type', 'App\Models\Cliente')
                   ->where('documentable_id', $cliente->id);
            })
            ->orWhere(function ($q2) use ($proyectosIds) {
                $q2->where('documentable_type', 'App\Models\Proyecto')
                   ->whereIn('documentable_id', $proyectosIds);
            });
        });
} */

    public function form(Form $form): Form
    {
       
        return $form
        ->schema([
            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->helperText('Selecciona el tipo general del documento.')
                ->relationship('tipo', 'nombre')
                ->required()
                ->live(),

            Select::make('subtipo_documento_id')
                ->label('Subtipo')
                ->helperText('Selecciona el subtipo del documento.')
                ->options(fn (callable $get) => \App\Models\DocumentoSubtipo::where('documento_categoria_id', $get('tipo_documento_id'))->pluck('nombre', 'id'))
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
                ->moveFiles()
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
                ->placeholder('Se rellenará automáticamente con el nombre del archivo')
                ->helperText('Puedes modificarlo si lo deseas.'),

            Textarea::make('observaciones')
                ->label('Observaciones')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->columns([
                // 🧑 Subido por
                TextColumn::make('user.name')
                    ->label('Subido por')
                    ->getStateUsing(function ($record) {
                        $user = $record->user;

                        if (! $user) return 'Usuario desconocido';

                        $nombre = $user->full_name ?? $user->name;
                        $tipo = $user->tipoDeUsuario();

                        if ($tipo === 'Trabajador') {
                            $roles = $user->roles->pluck('name')->implode(', ');
                            return "{$nombre} (Trabajador: {$roles})";
                        }

                        return "{$nombre} ({$tipo})";
                    }),

                // 📁 Tipo
                TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($record) => $record->tipo->color ?? 'gray'),

                // 📂 Subtipo
                TextColumn::make('subtipo.nombre')
                    ->label('Subtipo')
                    ->badge()
                    ->color('gray'),

                // 📄 Nombre archivo
                TextColumn::make('ruta')
                    ->label('Archivo')
                    ->url(fn ($record) => Storage::url($record->ruta), true)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn ($record) => $record->nombre),

                // 📝 Observaciones
                TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(30),

                // ✅ Verificado
                IconColumn::make('verificado')
                    ->label('Verificado')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->action(function ($record, $livewire) {
                        if (! auth()->user()->can('verificado_documento')) {
                            Notification::make()
                                ->title('No tienes permiso para verificar este documento.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->verificado = ! $record->verificado;
                        $record->save();

                        Notification::make()
                            ->title($record->verificado ? 'Documento verificado' : 'Verificación retirada')
                            ->success()
                            ->send();
                    })
                    ->tooltip(fn ($record) => $record->verificado
                        ? 'Marcar como NO verificado'
                        : 'Marcar como verificado')
                    ->extraAttributes(['style' => 'cursor: pointer;']),

                // 📎 Icono MIME
                IconColumn::make('mime_type')
                    ->label('Tipo')
                    ->icon(function ($record) {
                        $mime = $record->mime_type ?? '';
                        $extension = strtolower(pathinfo($record->ruta, PATHINFO_EXTENSION));

                        return match (true) {
                            $mime === 'image/png' || $extension === 'png' => 'icon-png',
                            $mime === 'image/jpeg' && $extension === 'jpg' => 'icon-jpg',
                            $mime === 'image/jpeg' && $extension === 'jpeg' => 'icon-jpeg',
                            $mime === 'application/pdf' => 'icon-pdf',
                            in_array($mime, [
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ]) => 'icon-doc',
                            in_array($mime, [
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]) => 'icon-excel',
                            default => 'heroicon-o-question-mark-circle',
                        };
                    })
                    ->tooltip(fn ($record) => $record->mime_type ?? 'Desconocido')
                    ->color(function ($record) {
                        $mime = $record->mime_type ?? '';

                        return match (true) {
                            str_starts_with($mime, 'image/') => 'info',
                            $mime === 'application/pdf' => 'danger',
                            in_array($mime, [
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ]) => 'primary',
                            in_array($mime, [
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]) => 'success',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                   SelectFilter::make('documentable_type')
                ->label('Tipo de asociado')
                ->options([
                    'App\Models\Cliente' => 'Cliente',
                    'App\Models\Proyecto' => 'Proyecto',
                ]),
                SelectFilter::make('tipo_documento_id')
                ->label('Tipo')
                ->relationship('tipo', 'nombre'),

                SelectFilter::make('subtipo_documento_id')
                ->label('Subtipo')
                ->relationship('subtipo', 'nombre'),

                 TernaryFilter::make('verificado')
                ->label('Verificado')
                ->trueLabel('Solo verificados')
                ->falseLabel('Solo no verificados'),

                DateRangeFilter::make('created_at')
                ->label('Subido en'),
            ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(7)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $user = Auth::user();
                    $data['user_id'] = $user->id;
                    $data['cliente_id'] = $this->getOwnerRecord()->id;
                    $data['mime_type'] = Storage::disk('public')->mimeType($data['ruta']);
                    //  $data['nombre'] = basename($data['ruta']);
              
                  
                      // Comprobar si el usuario está asociado a un trabajador
                      if ($user->trabajador) {  // Esto asegura que el usuario tiene un trabajador asociado
                          $data['verificado'] = true;  // Si es trabajador, el documento será verificado
                      } else {
                          $data['verificado'] = false;  // Si no es trabajador, no será verificado
                      }
                    return $data;
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ViewAction::make()
                ->label('Ver')
                ->url(fn ($record) => route('filament.admin.resources.documentos.view', ['record' => $record->id]))
                ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

   
}
