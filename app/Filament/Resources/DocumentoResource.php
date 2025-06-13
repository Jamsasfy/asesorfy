<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentoResource\Pages;
use App\Filament\Resources\DocumentoResource\Widgets\DocumentoStats;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\DocumentoCategoria;
use App\Models\DocumentoSubtipo;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Joaopaulolndev\FilamentPdfViewer\Infolists\Components\PdfViewerEntry;
use Filament\Infolists\Components\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class DocumentoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'icon-databasesearch-o';

  //  protected static ?string $navigationIcon = 'icon-customer';
    protected static ?string $navigationGroup = 'Documentos y BBDD';
    protected static ?string $navigationLabel = 'Documentos de Clientes';
    protected static ?string $modelLabel = 'Documento';
    protected static ?string $pluralModelLabel = 'Todos los Documentos';



    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'verificado',
        ];
    }

    public static function getEloquentQuery(): Builder
{
    /** @var \App\Models\User|null $user */
    $user = Auth::user();
    $query = static::getModel()::query(); // Comienza con la consulta del modelo del recurso

    if (!$user) {
        return $query->whereRaw('1 = 0');
    }

    // Descomenta esta línea para depurar quién está accediendo y qué roles tiene:
    // dd('User in DocumentoResource::getEloquentQuery():', $user->email, $user->getRoleNames()->toArray());

    if ($user->hasRole('super_admin')) {
        return $query;
    }

    if ($user->hasRole('asesor')) {
        return $query->whereHas('cliente', function (Builder $subQuery) use ($user) {
            $subQuery->where('asesor_id', $user->id);
        });
    }

    return $query->whereRaw('1 = 0'); // Default: no data for other roles
}



    public static function shouldRegisterNavigation(): bool
{
    /** @var \App\Models\User|null $user */
    $user = auth()->user();

    if (!$user) {
        return false;
    }

    // Permitir si es super_admin
    if ($user->hasRole('super_admin')) {
        return true;
    }

    // Permitir si es asesor Y tiene el permiso para ver cualquier documento
    // (la consulta luego se encargará de filtrar cuáles ve)
    if ($user->hasRole('asesor')) {
        return $user->can('view_any_documento');
    }  
    if ($user->hasRole('coordinador')) {
         return $user->can('view_any_documento');
    } 

    return false; // Por defecto, no mostrar para otros roles
}


    
   

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            
            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->helperText('Selecciona el tipo generico o familia del documento que vas a subir al cliente.')
                ->relationship('tipo', 'nombre')
                ->required()
                ->live(), // <- IMPORTANTE
            Select::make('subtipo_documento_id')
                ->label('Subtipo')
                ->helperText('Selecciona el subtipo. Si no existe, contacta con tu superior.')
                ->options(fn (callable $get) => \App\Models\DocumentoSubtipo::where('documento_categoria_id', $get('tipo_documento_id'))->pluck('nombre', 'id'))
                ->reactive()
                ->required()
                ->searchable()
                ->placeholder('Selecciona primero el tipo'),
          
            FileUpload::make('ruta')
                ->label('Archivo')
                ->disk('public')
                ->directory('documentos') // o la carpeta que uses
                ->maxSize(32768) // 32 MB
                ->required()
                ->moveFiles() // ✅ Esto evita la subida inmediata
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                    'application/msword', // .doc
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // ✅ .docx
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->preserveFilenames(false)
                ->visibility('public')
                ->visible(fn (string $context) => $context === 'create') // 👈 Aquí está la clave
             
                ->validationMessages([
                    'required' => 'Por favor, selecciona un archivo.',
                    'accepted_file_types' => 'Solo se permiten PDF, imágenes, Word y Excel.',
                    'max' => 'El archivo excede el tamaño máximo de 32 MB.',
                ])
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $set('nombre', $state->getClientOriginalName()); // ✅ guarda el nombre real
                    }
                }),
            TextInput::make('nombre')
                ->label('Nombre del documento')
                ->required()
               // ->searchable()
                ->maxLength(255)
                ->placeholder('Se rellenará automáticamente con el nombre del archivo')
                ->helperText('Puedes modificar el nombre si lo deseas.'),
            Textarea::make('observaciones')
                ->label('Observaciones')
                ->columnSpanFull(),

             // Selección de cliente solo visible para roles internos
        Select::make('cliente_id')
        ->label('Cliente')      
         ->relationship(
    name: 'cliente',
    titleAttribute: 'razon_social',
    modifyQueryUsing: function (Builder $query) {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user) {
            // Para depurar qué roles tiene el usuario actual (puedes descomentar temporalmente):
            // \Illuminate\Support\Facades\Log::info('Usuario en form cliente_id:', ['email's => $user->email, 'roles' => $user->getRoleNames()->toArray()]);

            if ($user->hasRole('super_admin')) {
                // El super_admin ve todos los clientes, no se añade ningún filtro aquí.
                // La consulta base de la relación se usará tal cual.
            } elseif ($user->hasRole('asesor')) {
                // Si NO es super_admin PERO SÍ es asesor, filtramos por sus clientes.
                $query->where('asesor_id', $user->id);
            }
           
        } else {
            // No hay usuario autenticado (no debería ocurrir en Filament)
            $query->whereRaw('1 = 0'); // No muestra nada
        }
    }
)
        ->searchable()
        ->preload()
        ->required()
            ->reactive(),


        Select::make('documentable_type')
                ->label('¿A qué va asociado?')
                ->options([
                    'App\Models\Cliente' => 'Cliente',
                    'App\Models\Proyecto' => 'Proyecto',
                ])
                ->required()
                ->reactive(),


            // 2️⃣ Selecciona el registro concreto del modelo elegido
            Select::make('documentable_id')
            ->label('Selecciona el registro')
            ->required()
            ->searchable()
            ->options(function (callable $get) {
                $type = $get('documentable_type');
                $clienteId = $get('cliente_id');

                if ($type === 'App\Models\Cliente' && $clienteId) {
                    // Solo deja elegir el cliente seleccionado
                    $cliente = \App\Models\Cliente::find($clienteId);
                    return $cliente ? [$cliente->id => $cliente->razon_social] : [];
                }
                if ($type === 'App\Models\Proyecto' && $clienteId) {
                    // Filtra proyectos SOLO de ese cliente
                    return \App\Models\Proyecto::where('cliente_id', $clienteId)
                        ->pluck('nombre', 'id');
                }
                return [];
            })
            ->visible(fn (callable $get) => filled($get('documentable_type')))
            ->helperText('Primero selecciona cliente y después el tipo de asociación.'),

    ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
    ->schema([
        Group::make([
            Section::make('Información general')
                ->schema([
                    TextEntry::make('nombre')
                        ->label('📄 Nombre')
                        ->weight(FontWeight::Bold),

                    TextEntry::make('tipo.nombre')
                        ->label('📁 Tipo')
                        ->badge()
                        ->color(fn ($record) => $record->tipo->color ?? 'gray'),

                    TextEntry::make('subtipo.nombre')
                        ->label('📂 Subtipo')
                        ->badge()
                        ->color('info'),

                    TextEntry::make('observaciones')
                        ->label('📝 Observaciones'),

                    TextEntry::make('cliente.razon_social')
                        ->label('Cliente')
                        ->state(function ($record) {
                            $nombre = $record->cliente?->razon_social ?? 'No asignado';
                            $url = route('filament.admin.resources.clientes.edit', ['record' => $record->cliente_id]);

                            return "<a href='{$url}' target='_blank' class='text-yellow-500 underline font-semibold'>🏢 $nombre</a>";
                        })
                        ->html(),
                ])
                ->columns(5)
                ->columnSpan(2), // 2/3

            Section::make('Estado')
                ->schema([
                   
                        TextEntry::make('verificado')
                            ->label('Verificado')
                            ->state(function ($record) {
                                return $record->verificado
                                    ? '<span style="color: #16a34a; font-weight: bold;">✅ Verificado</span>'
                                    : '<span style="color: #dc2626; font-weight: bold;">⚠️ No verificado</span>';
                            })
                            ->html()
                            ->suffixActions([
                                Action::make('toggle_verificacion')
                                ->label('')
                                ->icon(fn ($record) => $record->verificado ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                                ->iconButton()
                                ->color(fn ($record) => $record->verificado ? 'danger' : 'success')
                                ->tooltip(fn ($record) => $record->verificado ? 'Marcar como NO verificado' : 'Marcar como verificado')
                                ->requiresConfirmation()
                                ->modalHeading(fn ($record) => $record->verificado ? '¿Quitar verificación?' : '¿Verificar documento?')
                                ->modalDescription(fn ($record) => $record->verificado
                                    ? 'El documento se marcará como no verificado.'
                                    : 'Esto marcará el documento como verificado.')
                                ->modalSubmitActionLabel(fn ($record) => $record->verificado ? 'Quitar verificación' : 'Verificar')
                                ->action(function ($record) {
                                    $record->verificado = ! $record->verificado;
                                    $record->save();
                            
                                    Notification::make()
                                        ->title($record->verificado ? 'Documento verificado' : 'Verificación retirada')
                                        ->success()
                                        ->send();
                                })
                                ->visible(fn () => auth()->user()?->can('verificado_documento')),
                            
                                    

                                ]),
                               
                    TextEntry::make('user.name')
                        ->label('👤 Subido por')
                        ->state(function ($record) {
                            $user = $record->user;
                            if (! $user) return 'Usuario desconocido';

                            $nombre = $user->full_name ?? $user->name;
                            $tipo = $user->tipoDeUsuario();

                            if ($tipo === 'Trabajador') {
                                $roles = $user->roles->pluck('name')->implode(', ');
                                return "{$nombre} (Trabajador: {$roles})";
                            }

                            return "{$nombre} ({$tipo})";
                        })
                        ->weight(FontWeight::Bold),

                    TextEntry::make('created_at')
                        ->label('🕓 Subido el')
                        ->dateTime('d/m/Y - H:i')
                        ->weight(FontWeight::Bold),
                ])
                ->columns(3)
                ->columnSpan(1), // 1/3
        ])->columns(3) // Esta es la clave: controla layout interno
    ]),        
                        Section::make('Archivo')
                        ->schema([
                            // Enlace al archivo (para todos los tipos)
                            TextEntry::make('archivo_nombre')
                            ->label(false)
                            ->icon('heroicon-m-document-text')
                            ->iconColor('primary')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->state(fn ($record) => basename($record->ruta)) // solo el nombre del archivo
                            ->url(fn ($record) => Storage::url($record->ruta), true) // abre en nueva pestaña
                            ->openUrlInNewTab()
                            ->weight(FontWeight::Bold)
                            ->visible(fn ($record) => filled($record->ruta)),
                                // Botones para imagen
                            TextEntry::make('imagen_action_button')
                                ->label('Acciones imagen')
                               ->state(function ($record) {
                                    $url = Storage::url($record->ruta);
                                    return <<<HTML
                                        <div style="text-align: right; margin-bottom: 0.5rem;">
                                            <a href="$url" target="_blank" 
                                                style="background-color: #3b82f6; color: white; padding: 6px 12px; border-radius: 6px; font-size: 14px; text-decoration: none;">
                                                🔗 Abrir en nueva pestaña
                                            </a>
                                            <a href="$url" download 
                                                style="background-color: #10b981; color: white; padding: 6px 12px; border-radius: 6px; font-size: 14px; text-decoration: none; margin-left: 8px;">
                                                ⬇️ Descargar
                                            </a>
                                        </div>
                                    HTML;
                                })
                                ->html()
                                ->visible(fn ($record) => str_starts_with($record->mime_type, 'image/')),

                            // Previsualización de imágenes
                            TextEntry::make('preview_imagen')
                            ->label('Vista previa de imagen')
                            ->state(function ($record) {
                                $fullUrl = Storage::url($record->ruta);
                                $urlParts = explode('/', $fullUrl);
                                $filename = array_pop($urlParts); // Captura el nombre del archivo
                                $encodedName = rawurlencode($filename); // Espacios -> %20, etc.
                                $finalUrl = implode('/', $urlParts) . '/' . $encodedName;

                                return <<<HTML
                                    <style>
                                        .zoomable-image:hover {
                                            transform: scale(1.05);
                                        }
                                        .zoomable-image {
                                            transition: transform 0.3s ease;
                                            max-width: 100%;
                                            max-height: 600px;
                                            border-radius: 10px;
                                            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                                        }
                                    </style>
                                    <a href="$finalUrl" target="_blank" title="Abrir en nueva pestaña">
                                        <img src="$finalUrl" class="zoomable-image">
                                    </a>
                                HTML;
                            })
                            ->html()
                            ->visible(fn ($record) => str_starts_with($record->mime_type, 'image/')),
                 
                            PdfViewerEntry::make('preview_pdf')
                                ->label('Vista previa del PDF')
                                ->fileUrl(fn ($record) => Storage::url($record->ruta))
                                ->minHeight('700px')
                                ->visible(fn ($record) => $record->mime_type === 'application/pdf'),

                                TextEntry::make('otros_action_button')
                                ->label('Este archivo no se puede previsualizar, pero puedes descargarlo')
                                ->state(function ($record) {
                                    $url = Storage::url($record->ruta);
                                    return <<<HTML
                                        <div style="text-align: right; margin-bottom: 0.5rem;">
                                           
                                            <a href="$url" download 
                                               style="background-color: #10b981; color: white; padding: 6px 12px; border-radius: 6px; font-size: 14px; text-decoration: none; margin-left: 8px;">
                                                ⬇️ Descargar
                                            </a>
                                        </div>
                                    HTML;
                                })
                                ->html()
                                ->visible(fn ($record) =>
                                    filled($record->ruta)
                                    && filled($record->mime_type)
                                    && !str_starts_with($record->mime_type, 'image/')
                                    && $record->mime_type !== 'application/pdf'
                                ),   
                        ]),
                    
                ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->recordUrl(null)
        
        ->columns([
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
                   // A quién pertenece el documento
                   TextColumn::make('cliente.razon_social')
                   ->label('Cliente')
                   ->url(fn ($record) => route('filament.admin.resources.clientes.edit', ['record' => $record->cliente_id]))
                   ->openUrlInNewTab()
                   ->icon('icon-customer')
                   ->searchable()
                   ->iconPosition('before')
                   ->formatStateUsing(fn ($state) => $state ?? 'Cliente eliminado')
                   ->color('warning'), // Esto aplica el color amarillo en Filament
          
            
            TextColumn::make('tipo.nombre')
                ->label('Tipo')
                ->badge()
                ->color(fn ($record) => $record->tipo->color ?? 'gray'),

            TextColumn::make('subtipo.nombre')
                ->label('Subtipo')
                ->badge()
                ->color('gray'),
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
                    // Verificamos si el usuario tiene el permiso "verificado_documento"
                    if (! auth()->user()->can('verificado_documento')) {
                        Notification::make()
                            ->title('No tienes permiso para verificar este documento.')
                            ->danger()
                            ->send();
                        return;
                    }
                    // Si tiene permiso, alterna el estado de verificado
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


                IconColumn::make('mime_type')
                ->label('Tipo')
                ->icon(function ($record) {
                    $mime = $record->mime_type ?? '';
                    $extension = strtolower(pathinfo($record->ruta, PATHINFO_EXTENSION));
                
                    // Tipos de imagen diferenciados
                    if ($mime === 'image/png' || $extension === 'png') {
                        return 'icon-png'; // PNG
                    }
                
                    if ($mime === 'image/jpeg' && $extension === 'jpg') {
                        return 'icon-jpg'; // JPG
                    }
                
                    if ($mime === 'image/jpeg' && $extension === 'jpeg') {
                        return 'icon-jpeg'; // JPEG
                    }
                
                    // PDF
                    if ($mime === 'application/pdf') {
                        return 'icon-pdf';
                    }
                
                    // Word
                    if (in_array($mime, [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])) {
                        return 'icon-doc';
                    }
                
                    // Excel
                    if (in_array($mime, [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])) {
                        return 'icon-excel';
                    }
                
                    // Por defecto
                    return 'heroicon-o-question-mark-circle';
                })
                ->tooltip(fn ($record) => $record->mime_type ?? 'Desconocido')
                ->color(function ($record) {
                    $mime = $record->mime_type ?? '';
                
                    if (str_starts_with($mime, 'image/')) {
                        return 'info'; // Azul
                    }
                
                    if ($mime === 'application/pdf') {
                        return 'danger'; // Rojo
                    }
                
                    if (in_array($mime, [
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])) {
                        return 'primary'; // Azul oscuro
                    }
                
                    if (in_array($mime, [
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])) {
                        return 'success'; // Verde
                    }
                
                    return 'gray'; // Por defecto
                }),

                    TextColumn::make('created_at')
                        ->label('Subido el')
                        ->dateTime('d/m/Y H:i')
                        ->sortable(),

                ])
                ->defaultSort('created_at', 'desc')
            ->filters([
              /*   SelectFilter::make('user_id')
                ->label('Subido por')
                ->options(fn () => User::pluck('name', 'id')) // o full_name si lo usas
                ->searchable()
                ->native(false), */
            SelectFilter::make('cliente_id')
                ->label('Cliente')
                ->options(Cliente::pluck('razon_social', 'id'))
                ->searchable()
                ->native(false),       
            TernaryFilter::make('verificado')
                ->label('Verificado')
                ->trueLabel('Solo verificados')
                ->falseLabel('Solo no verificados')
                ->native(false),
    
            // 📂 Tipo de documento
            SelectFilter::make('tipo_documento_id')
                ->label('Tipo')
                ->options(DocumentoCategoria::pluck('nombre', 'id'))
                ->searchable()
                ->native(false),
              // 🧾 Subtipo de documento
            SelectFilter::make('subtipo_documento_id')
                ->label('Subtipo')
                ->options(DocumentoSubtipo::pluck('nombre', 'id'))
                ->searchable()
                ->native(false),
             // 🧑‍💼 Cliente
           
            SelectFilter::make('mime_type')
                ->label('Buscar por extensión')
                
                ->options([
                    'application/pdf' => 'PDF',
                    'image/png' => 'Imagen PNG',
                    'image/jpeg' => 'Imagen JPG/JPEG',
                    'application/msword' => 'Word (doc)',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (docx)',
                    'application/vnd.ms-excel' => 'Excel (xls)',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (xlsx)',
                ]),

                SelectFilter::make('documentable_type')
                ->label('Tipo de asociado')
                ->options([
                    'App\Models\Cliente' => 'Cliente',
                    'App\Models\Proyecto' => 'Proyecto',
                ]),
               // ->native(false),     
            DateRangeFilter::make('created_at')
                ->label('Subido')
                ->placeholder('Rango de fechas a buscar'),  
            DateRangeFilter::make('updated_at')
                ->label('Actualizado')
                ->placeholder('Rango de fechas a buscar'),      
            ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(7)
            ->actions([
                Tables\Actions\EditAction::make(),
                ViewAction::make()
                ->label('Ver'), // Automáticamente usa ViewDocumento
               
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
            'index' => Pages\ListDocumentos::route('/'),
            'create' => Pages\CreateDocumento::route('/create'),
            'edit' => Pages\EditDocumento::route('/{record}/edit'),
            'view' => Pages\ViewDocumento::route('/{record}/view'),

        ];
    }

    /* public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\DocumentoResource\Widgets\DocumentoStats::class,
        ];
    } */


}
