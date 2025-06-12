<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProyectoResource\Pages;
use App\Filament\Resources\ProyectoResource\RelationManagers;
use App\Models\Proyecto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select; // Importa Select
use Filament\Forms\Components\TextInput; // Importa TextInput
use Filament\Forms\Components\Textarea; // Importa Textarea
use Filament\Forms\Components\DatePicker; // Importa DatePicker
use Filament\Forms\Components\DateTimePicker; // Importa DateTimePicker
use Filament\Forms\Components\Section; // Importa Section
use Filament\Tables\Columns\TextColumn; // Importa TextColumn

use App\Enums\ProyectoEstadoEnum; // Si usas el Enum para estados
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Infolist;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Actions\Action as ActionInfolist;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions; // <<< Importa este para el grupo de acciones
use Filament\Forms\Components\Actions\Action as FormAction;
use Livewire\Component; // <<< NUEVO IMPORT: Para la clase Livewire\Component (para el dispatch)
use Filament\Forms\Set; // <<< ASEGÚRATE DE QUE ESTA LÍNEA ESTÉ AQUÍ




class ProyectoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Proyecto::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase'; // Icono de maletín
    protected static ?string $navigationGroup = 'Gestión PROYECTOS'; // Nuevo grupo de navegación
    protected static ?string $modelLabel = 'Proyecto';
    protected static ?string $pluralModelLabel = 'Proyectos';

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
  public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $query = parent::getEloquentQuery()->with(['cliente']); 
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // <<< CAMBIO AQUI: super_admin O coordinador ven todos los proyectos
        if ($user->hasRole('super_admin') || $user->hasRole('coordinador')) {
            return $query; // Super admin Y coordinador ven todos los registros
        }

        // <<< CAMBIO AQUI: 'asesor' solo ve los proyectos asignados a él
        if ($user->hasRole('asesor')) {
            return $query->where('user_id', $user->id); 
        }
        
        // Por defecto: cualquier otro rol o no autenticado no ve nada
        return $query->whereRaw('1 = 0');
    }



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos del Proyecto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Proyecto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'razon_social')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Select::make('user_id')
                            ->label('Asesor Asignado')
                            ->relationship('user', 'name', fn (Builder $query) => 
                                // Asume que solo los 'comercial' y 'super_admin' pueden ser asesores asignados a proyectos
                                $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['comercial', 'super_admin']))
                            )
                            ->searchable()
                            ->preload()
                            ->nullable() // Puede no estar asignado inicialmente
                            ->columnSpan(1),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(ProyectoEstadoEnum::class) // Usa el Enum para las opciones
                            ->native(false) // Para una mejor UI en el Select
                            ->required()
                            ->default(ProyectoEstadoEnum::Pendiente->value) // Estado por defecto
                            ->columnSpan(1),

                        DateTimePicker::make('fecha_finalizacion')
                            ->label('Fecha de Finalización Real')
                            ->nullable()
                            ->native(false)
                            ->disabled(fn(Forms\Get $get) => $get('estado') !== ProyectoEstadoEnum::Finalizado->value) // Deshabilitado si no está finalizado
                            ->helperText('Se establece automáticamente al marcar el estado como "Finalizado".')
                            ->columnSpan(1),

                        // Campos opcionales para vincular a Venta/Servicio/VentaItem
                        Select::make('venta_id')
                            ->label('Venta de Origen')
                            ->relationship('venta', 'id') // Asume que ID es suficiente, o puedes usar un accesor
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpan(1),

                        Select::make('venta_item_id')
                            ->label('Item de Venta Recurrente')
                            ->relationship('ventaItem', 'id') // Asume que ID es suficiente
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Item de venta recurrente cuya suscripción se activa al finalizar este proyecto.')
                            ->columnSpan(1),

                        // Puedes añadir Select::make('servicio_id') si es necesario

                        DatePicker::make('fecha_inicio_estimada')
                            ->label('Inicio Estimado')
                            ->native(false)
                            ->nullable()
                            ->columnSpan(1),

                        DatePicker::make('fecha_fin_estimada')
                            ->label('Fin Estimado')
                            ->native(false)
                            ->nullable()
                            ->columnSpan(1),

                        Textarea::make('descripcion')
                            ->label('Descripción del Proyecto')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Proyecto')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente.razon_social')
                    ->label('Cliente')
                    // <<< CAMBIO AQUI: Convertir a enlace y colorear
                    ->url(fn (Proyecto $record): ?string => 
                        $record->cliente_id
                            ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                            : null
                    )
                    ->color('warning') // Color amarillo para el enlace
                    ->openUrlInNewTab() // Abrir en nueva pestaña
                    // FIN CAMBIO AQUI
                    ->searchable()
                    ->sortable(),

               TextColumn::make('user.name')
                    ->label('Asesor')
                    ->searchable()
                    ->badge()
                    ->sortable()
                     // <<< CAMBIO CLAVE AQUI: Usar getStateUsing para controlar el valor base
                    ->getStateUsing(function (Proyecto $record): ?string {
                        // Si no hay user_id (null en DB), devuelve 'Sin asignar' como el estado
                        if (is_null($record->user_id)) {
                            return 'Sin asignar';
                        }
                        // Si hay user_id, devuelve el nombre del usuario
                        // Asegúrate de que la relación 'user' esté cargada si es necesaria
                        return $record->user->name ?? null; // Devuelve el nombre o null si la relación user es null por alguna razón
                    })
                    // Ahora, formatStateUsing ya no necesita la condición is_null($record->user_id)
                    // porque getStateUsing ya ha forzado 'Sin asignar' si es null.
                    ->formatStateUsing(function ($state): string {
                        // $state ya será 'Sin asignar' o el nombre del usuario
                        return $state;
                    })
                    // Color del badge: 'info' (azul) si asignado, 'warning' (amarillo) si no
                    ->color(function ($state): string {
                        // $state ya será 'Sin asignar' o el nombre del usuario
                        if ($state === 'Sin asignar') {
                            return 'warning'; // Amarillo para 'Sin asignar'
                        }
                        return 'info'; // Azul para el nombre del asesor
                    }),

                TextColumn::make('venta_id')
                    ->label('ID Venta')
                    ->url(fn (Proyecto $record): ?string => 
                        $record->venta_id ? VentaResource::getUrl('edit', ['record' => $record->venta_id]) : null
                    )
                    ->color(fn (Proyecto $record): string => $record->venta_id ? 'primary' : 'secondary')
                    ->openUrlInNewTab() // Abrir en nueva pestaña
                    ->searchable()
                    ->sortable(),
                
                // <<< AÑADIDO: ID de Item de Venta (para auditoría específica)
                TextColumn::make('venta_item_id')
                    ->label('ID Item Venta')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por defecto
        // <<< AÑADIDO: Servicio Asociado (el que disparó el proyecto)
                TextColumn::make('servicio.nombre')
                    ->label('Servicio Activador')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'primary' => ProyectoEstadoEnum::Pendiente->value,
                        'warning' => ProyectoEstadoEnum::EnProgreso->value,
                        'success' => ProyectoEstadoEnum::Finalizado->value,
                        'danger'  => ProyectoEstadoEnum::Cancelado->value,
                    ])
                    ->sortable(),

                TextColumn::make('fecha_finalizacion')
                    ->label('Finalizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('fecha_inicio_estimada')
                    ->label('Inicio Estimado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fecha_fin_estimada')
                    ->label('Fin Estimado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtro por Cliente
                Tables\Filters\SelectFilter::make('cliente_id')
                    ->relationship('cliente', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Cliente'),

                // Filtro por Asesor Asignado
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name', fn (Builder $query) => 
                        $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['comercial', 'super_admin']))
                    )
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Asesor'),

                // Filtro por Estado del Proyecto
                Tables\Filters\SelectFilter::make('estado')
                    ->options(ProyectoEstadoEnum::class) // Usa el Enum para las opciones
                    ->native(false)
                    ->label('Filtrar por Estado'),

                // Filtro por Fecha de Finalización Real
                DateRangeFilter::make('fecha_finalizacion')
                   
                    ->label('Fecha Finalización'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                ->openUrlInNewTab(),
                Tables\Actions\ViewAction::make()
                 ->openUrlInNewTab(),
                  // <<< AÑADIDO: Acción para Asignar Asesor
               Action::make('assign_assessor')
                    ->label('Asignar Asesor')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->modalHeading('Asignar Asesor al Proyecto')
                    ->modalSubmitActionLabel('Asignar')
                    ->modalWidth('md')
                    ->form([
                        // <<< AÑADIDO: Placeholder para mostrar el asesor del cliente
                        Placeholder::make('asesor_cliente_info')
                            ->label('') // No necesitamos etiqueta visible para este placeholder
                            ->content(function (Proyecto $record): HtmlString {
                                $asesorClienteNombre = $record->cliente->asesor->name ?? 'No asignado'; // Asume relación cliente->asesor->name
                                $color = $record->cliente->asesor ? '#16a34a' : '#f59e0b'; // green-600 (info) o amber-500 (warning)

                                return new HtmlString("
                                    <div style='
                                        background-color: {$color}; 
                                        color: white; 
                                        padding: 0.75rem; 
                                        border-radius: 0.375rem; 
                                        font-weight: bold; 
                                        font-size: 0.9rem;
                                        text-align: center;
                                        margin-bottom: 1rem;
                                    '>
                                        Asesor del Cliente: {$asesorClienteNombre}
                                    </div>
                                ");
                            }),
                          // <<< AÑADIDO: Botón para Asignarse a sí mismo
                     Actions::make([
                            FormAction::make('assign_self')
                                ->label('Asignar al mismo asesor')
                                ->icon('heroicon-m-user-circle')
                                ->color('warning')
                                ->outlined()
                                // No es de tipo submit, solo rellena el campo
                                ->action(function (Set $set): void { 
                                    $set('user_id', Auth::id()); // Rellena el select con el ID del usuario logueado
                                    // NO intentamos submit() aquí. El usuario tendrá que hacer clic en 'Asignar'.
                                    // Opcional: podrías añadir una notificación aquí para indicar que se ha rellenado
                                    // Notification::make()->title('Asesor seleccionado')->body('Ahora haz clic en "Asignar".')->info()->send();
                                }),
                        ])->fullWidth(), // Ocupa todo el ancho disponible para el botón
                        // FIN AÑADIDO

                        Select::make('user_id')
                            ->label('Seleccionar Asesor para el Proyecto') // Etiqueta más clara
                            ->relationship('user', 'name', fn (Builder $query) => 
                                $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['asesor', 'super_admin']))
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (?Proyecto $record): ?int => $record?->user_id),
                    ])
                    ->action(function (array $data, Proyecto $record): void {
                        $record->user_id = $data['user_id'];
                        $record->save();

                        Notification::make()
                            ->title('Asesor asignado correctamente')
                            ->success()
                            ->send();
                    }),
                ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

     // <<< AÑADIDO: Método infolist para la página de vista detallada
  public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([




                Grid::make(3)->schema([
            // Info básica
            InfoSection::make(fn (Proyecto $record): string => 'Proyecto para ' . ($record->cliente->razon_social ?? 'Cliente Desconocido'))
                ->schema([
                    TextEntry::make('nombre')  
                   ->label(new HtmlString('<span class="font-semibold">Nombre del Proyecto</span>'))
                                ->copyable()
                                ->weight('bold')
                                ->color('primary')                                        
                        ->columnSpan(2),
                    TextEntry::make('cliente.telefono_contacto')      
                    ->label('Teléfono Cliente')
                      ->copyable()
                                ->weight('bold')
                                ->color('primary')       
                        ->copyable(),
                    TextEntry::make('cliente.email_contacto')      
                        ->label('Email Cliente')
                                ->weight('bold')
                                ->color('primary')       
                    ->copyable()->columnSpanFull(), // Ocupa todo el ancho de la sección

                   TextEntry::make('acceso_perfil_cliente') // Nombre de campo ficticio o accesor
                                ->label(new HtmlString('<span class="font-semibold">Acceso al Perfil del Cliente</span>')) 
                                ->state(fn (Proyecto $record) => $record->cliente->razon_social ?? 'Cliente no disponible') // Mostrar el nombre del cliente o un texto
                                ->url(fn (Proyecto $record): ?string => 
                                    // Genera la URL al recurso Cliente, a su página de vista
                                    $record->cliente_id ? ClienteResource::getUrl('view', ['record' => $record->cliente_id]) : null
                                )
                                ->openUrlInNewTab() // <<< Abrir en una nueva pestaña
                                ->color('warning') // Color azul para el enlace
                              //  ->copyable() // Permite copiar el texto del enlace (el nombre del cliente)
                                ->weight('bold')
                                ->icon('heroicon-m-arrow-top-right-on-square')                 
                                ->columnSpanFull(), // Ocupa todo el ancho
    
                ])
                ->columns(3)
                ->columnSpan(1),

            // Estado y asignación
            InfoSection::make('Estado & Asignación')
                ->schema([
                    TextEntry::make('venta.comercial.name')
                    ->label('Comercial')
                        ->badge()
                        ->color('primary'),

                    TextEntry::make('created_at')
                    ->label('Proyecto creado')
                        
                        ->dateTime('d/m/y H:i'),
                     // <<< CAMBIO CLAVE AQUI: Diseño mejorado para Venta de Origen
                            TextEntry::make('venta.id')
                                ->label(new HtmlString('<span class="font-semibold">Venta de Origen</span>'))
                                ->badge() // Convertir a badge
                                // Formato que muestra el icono, el texto y el ID
                                ->formatStateUsing(function ($state, Proyecto $record): string {
                                    if ($record->venta_id) {
                                        return 'Venta #' . $state; // 'state' es el ID de la venta
                                    }
                                    return 'No asociada'; // Texto si no hay venta
                                })
                                ->url(fn (Proyecto $record): ?string => 
                                    $record->venta_id ? VentaResource::getUrl('edit', ['record' => $record->venta_id]) : null
                                )
                                ->openUrlInNewTab() // Abrir en nueva pestaña
                                ->color(function ($state, Proyecto $record): string {
                                    // Color del badge: 'warning' si tiene venta, 'secondary' (gris) si no
                                    if ($record->venta_id) {
                                        return 'warning'; // Amarillo para ventas asociadas
                                    }
                                    return 'secondary'; // Gris para 'No asociada'
                                })
                                ->copyable() // Permite copiar el texto del badge
                                ->weight('bold')
                                ->icon(fn (Proyecto $record): ?string => // Icono para el badge
                                    $record->venta_id ? 'heroicon-m-link' : null // Icono de link si hay venta
                                ),

                    TextEntry::make('user.name')
                         ->label('Asesor Asignado')
                                ->badge() // Esto hace que se muestre como un badge
                                ->getStateUsing(fn (Proyecto $record): string => $record->user?->name ?? '⚠️ Sin asignar') // Este define el texto
                                ->color(fn (string $state): string => 
                                    str_contains($state, 'Sin asignar') ? 'warning' : 'info' // Este define el color
                                ),

                        TextEntry::make('estado')
                       
                        ->badge(),
                       
                         TextEntry::make('estado_servicios_recurrentes_venta_placeholder') 
                                ->label(new HtmlString('<span class="font-semibold">Estado de Servicios Recurrentes en esta Venta</span>'))
                                ->default('Las suscripciones se mostrará aquí una vez implementada la lógica.') // Mensaje claro
                               
                                ->icon('heroicon-o-exclamation-triangle') // Icono de advertencia
                                ->color('gray'),
                  ])
                
                ->columns(3)
                ->columnSpan(1),

            // Agenda
            InfoSection::make('Agenda & Gestión')
                ->schema([
                    TextEntry::make('updated_at')
                   
                        ->dateTime('d/m/y H:i'),

                        TextEntry::make('agenda')
                       
                        ->dateTime('d/m/y H:i'),
                        
                       

                        // --- Fecha de cierre ---
                        InfoSection::make('Interacciones')
                        ->schema([
                           
                            // Acción COMPLETA de LLAMADA ✔️
                            TextEntry::make('llamadas')
                                ->label('📞 Llamadas')
                                ->size('xl')
                                ->weight('bold'),
                            
                            
                            // Acción COMPLETA de EMAIL ✔️
                            TextEntry::make('emails')
                                ->label('📧 Emails')
                                ->size('xl')
                                ->weight('bold'),
                            
                            
                            // Acción COMPLETA de CHAT ✔️
                            TextEntry::make('chats')
                                ->label('💬 Chats')
                                ->size('xl')
                                ->weight('bold'),
                            
                            
                          
                            TextEntry::make('otros_acciones')
                                ->label('📎 Otros')
                                ->size('xl')
                                ->weight('bold'),
                            
                            
                         
                            
                        

                    TextEntry::make('total')
                        ->label('🔥 Total'),
                        ])
                        ->columns(5)
                        ->columnSpan(2),
                ])
                ->columns(2)
                ->columnSpan(1),
        ]),

       
        InfoSection::make('🗨️ Comentarios')
        //boton de añadir nuevo comentario
        ->headerActions([
            ActionInfolist::make('anadir_comentario')
                ->label('📝 Añadir comentario nuevo')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->modalHeading('Nuevo comentario')
                ->modalSubmitActionLabel('Guardar comentario')
                ->form([
                    Textarea::make('contenido')
                        ->label('Escribe el comentario')
                        ->required()
                        ->rows(4)
                        ->placeholder('Escribe aquí tu comentario...')
                ])
                ->action(function (array $data, Proyecto $record) {
                    $record->comentarios()->create([
                        'user_id' => auth()->id(),
                        'contenido' => $data['contenido'],
                    ]);
        
                    Notification::make()
                        ->title('Comentario guardado')
                        ->success()
                        ->send();
                }),

              


        ])
        ->schema([
            RepeatableEntry::make('comentarios')
                ->label(false)
                ->contained(false)
               // ->reverseItems()
                ->schema([
                    TextEntry::make('contenido')
                        ->html()
                        ->label(false)
                        ->state(function ($record) {
                            $usuario = $record->user?->name ?? 'Usuario';
                            $contenido = $record->contenido;
                            $fecha = $record->created_at?->format('d/m/Y H:i') ?? '';
                        
                            return '
        <div style="
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: #dcfce7;
            color: #1f2937;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            margin: 0.5rem 0;
            font-size: 0.95rem;
            line-height: 1.4;
            flex-wrap: wrap;
        ">
            <span style="font-weight: 600;">🧑‍💼 ' . e($usuario) . '</span>
            <span>' . e($contenido) . '</span>
            <span style="font-size: 0.8rem; color: #6b7280;">🕓 ' . e($fecha) . '</span>
        </div>
    ';
                        })
                ])
                //->columnSpanFull()
                ->visible(fn (Proyecto $record) => $record->comentarios->isNotEmpty()),
        ])





               
            ]);
    }




    public static function getRelations(): array
    {
        return [
            // Aquí vamos a añadir el RelationManager para comentarios
            // RelationManagers\ComentariosRelationManager::class, // Lo crearemos en el siguiente paso
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProyectos::route('/'),
          //LOS PROYECTOS SE CREAN DE FORMA AUTOMATICA DESDE LA VENTA POR EL TIPO DE SERVICIO  'create' => Pages\CreateProyecto::route('/create'),
            'edit' => Pages\EditProyecto::route('/{record}/edit'),
            'view' => Pages\ViewProyecto::route('/{record}'), // Añadida ruta para la página de vista

        ];
    }

    public static function canCreate(): bool
    {
        // Solo permitir la creación directa a Super Admins si es necesario para casos excepcionales
        // O false para deshabilitarlo completamente para todos
        // return auth()->user()->hasRole('super_admin'); 
        return false; // Deshabilita el botón de crear para todos los roles
    }


}