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
use Carbon\Carbon;
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
use Filament\Forms\Set; // <<< ASEGÚRATE DE QUE ESTA LÍNEA ESTÉ AQUÍ
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log; // Para Log::error
use Filament\Forms\Components\Toggle; // Para el Toggle en los formularios de las acciones
use App\Enums\ClienteSuscripcionEstadoEnum;
use Filament\Infolists\Components\ViewEntry;

use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Collection;



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
            'assign_assessor',   // Permiso para asignar/cambiar asesor
            'unassign_assessor', // Permiso para quitar asesor
          
          
        ];
    }
  public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $query = parent::getEloquentQuery()                
                ->with(['cliente']); 
        
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

                        // AÑADIDO: Campo agenda
                            DateTimePicker::make('agenda')
                                ->label('Próximo Seguimiento')
                                ->native(false)
                                ->nullable()
                                ->minutesStep(30) // O el intervalo que prefieras
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
       //  ->striped()
        ->recordUrl(null)   
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
       
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
                    ->getStateUsing(fn ($record) => $record->estado?->value ?? $record->estado) // <--- esto saca el string del Enum
                    ->colors([
                        'primary' => 'pendiente',
                        'warning' => 'en_progreso',
                        'success' => 'finalizado',
                        'danger'  => 'cancelado',
                    ])
                    ->formatStateUsing(fn ($state) => \App\Enums\ProyectoEstadoEnum::tryFrom($state)?->getLabel() ?? $state)
                    ->sortable(),
                      TextColumn::make('agenda')
                    ->label('Próx. Seguimiento')
                    ->dateTime('d/m/y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false) // Visible por defecto
                    ->placeholder('Sin agendar'), // Texto si es null

                TextColumn::make('fecha_finalizacion')
                    ->label('Finalizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

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
                    ->label(fn (Proyecto $record): string => $record->user_id ? 'Cambiar Asesor' : 'Asignar Asesor')
                    ->icon('heroicon-o-user-plus')
                    ->color(fn (Proyecto $record): string => $record->user_id ? 'primary' : 'warning')
                    ->visible(fn ($record) => auth()->user()?->can('assign_assessor_proyecto'))


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
                                 ->visible(fn (Proyecto $record): bool => (bool)$record->cliente->asesor_id) // Visible solo si el cliente tiene asesor_id
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

                     Action::make('unassign_assessor')
                    ->label('Quitar Asesor')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger') // Color rojo
                   ->visible(fn (Proyecto $record): bool => 
                        (bool)$record->user_id && // Solo visible si ya hay un asesor
                        auth()->user()->can('unassign_assessor_proyecto') // Comprueba el permiso
                    )
                    ->requiresConfirmation() // Preguntar confirmación antes de desasignar                    
                    ->action(function (Proyecto $record): void {
                        $record->user_id = null; // Poner el asesor a null
                        $record->save();

                        Notification::make()
                            ->title('Asesor desasignado correctamente')
                            ->success()
                            ->send();
                    }),


                ])
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make(),

      
    ]),
])


;
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
                                    // ▼▼▼ CAMPO AÑADIDO ▼▼▼
                    TextEntry::make('venta.lead.demandado')
                            ->label(new HtmlString('<span class="font-semibold">Demandado del Lead</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')
                            ->placeholder('No informado') // Se mostrará si el campo está vacío
                            ->columnSpanFull(),              
    
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
                                ->label(new HtmlString('<span class="font-semibold">Estado Actual</span>'))
                                ->badge()
                                ->color(fn (\App\Enums\ProyectoEstadoEnum $state): string => match ($state->value) {
                                    \App\Enums\ProyectoEstadoEnum::Pendiente->value   => 'primary',
                                    \App\Enums\ProyectoEstadoEnum::EnProgreso->value  => 'warning',
                                    \App\Enums\ProyectoEstadoEnum::Finalizado->value  => 'success',
                                    \App\Enums\ProyectoEstadoEnum::Cancelado->value   => 'danger',
                                    default => 'gray',
                                })
                                ->suffixAction(
                                    ActionInfolist::make('cambiar_estado_proyecto')
                                        ->label('') // No label, solo icono
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('primary')
                                        ->modalHeading('Cambiar Estado del Proyecto')
                                        ->modalSubmitActionLabel('Guardar Estado')
                                        ->modalWidth('md')
                                        ->form([
                                            Select::make('estado') // Usar FormsSelect
                                                ->label('Nuevo Estado')
                                                ->options(\App\Enums\ProyectoEstadoEnum::class)
                                                ->native(false)
                                                ->required()
                                                ->default(fn (?Proyecto $record): ?string => $record?->estado?->value),
                                            Textarea::make('comentario_estado') // Usar FormsTextarea
                                                ->label('Comentario para el cambio de estado')
                                                ->rows(3)
                                                ->nullable()
                                                ->maxLength(500),
                                        ])
                                        ->action(function (array $data, Proyecto $record) {
                                            $nuevoEstado = \App\Enums\ProyectoEstadoEnum::tryFrom($data['estado']);
                                            if (!$nuevoEstado) {
                                                Notification::make()->danger()->title('Error Estado')->send();
                                                return;
                                            }

                                            // Lógica del Modelo:
                                            // 1. El hook 'updating' en Proyecto.php establecerá fecha_finalizacion si pasa a Finalizado.
                                            // 2. El hook 'updated' en Proyecto.php llamará a $proyecto->venta->checkAndActivateSubscriptions() (que está vacío por ahora).
                                            $record->estado = $nuevoEstado;
                                            $record->save(); // Guarda el cambio de estado y dispara los hooks.

                                            // Registrar comentario
                                            $comentarioTexto = 'Cambio de estado a: ' . $nuevoEstado->getLabel();
                                            if (!empty($data['comentario_estado'])) {
                                                $comentarioTexto .= "\n---\nObservación: " . $data['comentario_estado'];
                                            }
                                            $record->comentarios()->create([
                                                'user_id' => Auth::id(),
                                                'contenido' => $comentarioTexto,
                                            ]);

                                            Notification::make()->title('Estado del proyecto actualizado')->success()->send();
                                        })
                                ),

                    ViewEntry::make('resumen_venta_pendientes')
                   //  ->heading('Proyectos o servicios dependientes')
            ->view('filament.infolists.components.resumen-venta-pendientes')
            ->columnSpanFull(),

                  
                  ])
                
                ->columns(3)
                ->columnSpan(1),

               

            // Agenda
            InfoSection::make('Agenda & Gestión')
                ->schema([
           
                    // <<< CAMBIO CLAVE AQUI: Copiar exactamente la sintaxis que funciona en LeadResource
                     TextEntry::make('agenda')
                    ->label(new HtmlString('<span class="font-semibold">📆 Próxima cita</span>'))
                    ->dateTime('d/m/y H:i')
                    ->placeholder('Sin agendar')
                      //  ->default('Sin agendar')          // TRATA esto como un estado "real"
 // <- Esto es suficiente si el campo existe
                    ->suffixAction(
                        ActionInfolist::make('reagendar')
                            ->icon('heroicon-o-calendar-days')
                            ->form([
                                DateTimePicker::make('agenda')
                                    ->label('Nueva fecha de agenda')
                                    ->displayFormat('d/m/Y H:i')
                                    ->native(false)
                                    ->default(fn ($record) => $record->agenda ?? now())
                                    ->minutesStep(30),
                                        ])
                                        ->action(function (array $data, $record) {
                                            $record->agenda = $data['agenda'];
                                            $record->save();

                                            $fechaFormateada = \Carbon\Carbon::parse($data['agenda'])->format('d/m/Y H:i');
                                            $record->comentarios()->create([
                                                'user_id' => auth()->id(),
                                                'contenido' => '📅 Nueva agenda fijada para: ' . $fechaFormateada,
                                            ]);
                                            \Filament\Notifications\Notification::make()
                                                ->title('✅ Agenda actualizada')
                                                ->body('Se ha registrado la nueva fecha de agenda correctamente.')
                                                ->success()
                                                ->send();
                                        })
                        ),

                            // FIN CAMBIO CLAVE

                    TextEntry::make('updated_at')
                        ->label('Última Actualización')  
                        ->color('warning')
                       ->weight('bold')
                        ->dateTime('d/m/y H:i'),

                    TextEntry::make('fecha_finalizacion')
                        ->color('warning')
                       ->weight('bold')
                       ->placeholder('Aún no finalizado')
                        ->dateTime('d/m/y H:i'),
                        
                       

                        // --- Fecha de cierre ---
                        InfoSection::make('Interacciones')
                        ->schema([
                           
                            // Acción COMPLETA de LLAMADA ✔️
                        // Acción COMPLETA de LLAMADA
                        TextEntry::make('llamadas')
                            ->label('📞 Llamadas')
                            ->size('xl')
                            ->weight('bold')
                            ->alignment(alignment::Center)
                            ->suffixAction(
                                ActionInfolist::make('add_llamada')
                                    ->icon('heroicon-m-phone-arrow-up-right')
                                    ->color('primary')
                                    ->form([
                                        Toggle::make('respuesta') // Usar Toggle
                                            ->label('Contestado')
                                            ->default(false)
                                            ->helperText('Marca si el cliente ha contestado la llamada.')
                                            ->live(),
                                        Textarea::make('comentario')
                                            ->label('Comentario')
                                            ->rows(3)
                                            ->hint('Describe brevemente la llamada.')
                                            ->visible(fn (Forms\Get $get) => $get('respuesta') === true)
                                            ->required(fn (Forms\Get $get) => $get('respuesta') === true)
                                            ->maxLength(500),
                                        Toggle::make('agendar') // Usar Toggle
                                            ->label('Agendar nueva llamada')
                                            ->default(false)
                                            ->helperText('Programa una nueva cita de seguimiento.')
                                            ->live(),
                                        DateTimePicker::make('agenda')
                                            ->label('Fecha y hora de la nueva llamada')
                                            ->minutesStep(30)
                                            ->seconds(false)
                                            ->native(false)
                                            ->visible(fn (Forms\Get $get) => $get('agendar') === true)
                                            ->after(now())
                                            ->required(fn (Forms\Get $get) => $get('agendar') === true),
                                    ])
                                    ->action(function (array $data, \App\Models\Proyecto $record) {
                                        // Llamar al helper registrarInteraccion
                                        self::registrarInteraccion(
                                            $record,
                                            'llamadas',
                                            $data['comentario'] ?? '', // Contenido del comentario
                                            $data['respuesta'] ?? false, // ¿Fue contestada?
                                            $data['agendar'] ?? false, // ¿Agendar seguimiento?
                                            isset($data['agenda']) ? Carbon::parse($data['agenda']) : null // Fecha de agenda
                                        );
                                    })
                            ),
                            
                            
                           // Acción COMPLETA de EMAIL
                        TextEntry::make('emails')
                            ->label('📧 Emails')
                            ->size('xl')
                            ->weight('bold')
                            ->alignment(Alignment::Center)
                            ->suffixAction(
                                ActionInfolist::make('add_email')
                                    ->icon('heroicon-m-envelope-open')
                                    ->color('warning')
                                    ->form([
                                        Textarea::make('comentario')
                                            ->label('Comentario (opcional)')
                                            ->rows(3)
                                            ->hint('Describe el contenido del email enviado.')
                                            ->maxLength(500),
                                        Toggle::make('agendar')
                                            ->label('Agendar seguimiento')
                                            ->default(false)
                                            ->live(),
                                        DateTimePicker::make('agenda')
                                            ->label('Fecha de seguimiento')
                                            ->minutesStep(30)
                                            ->seconds(false)
                                            ->native(false)
                                            ->visible(fn (Forms\Get $get) => $get('agendar') === true)
                                            ->after(now()),
                                    ])
                                    ->action(function (array $data, \App\Models\Proyecto $record) {
                                        self::registrarInteraccion(
                                            $record,
                                            'emails',
                                            $data['comentario'] ?? '',
                                            true, // Se asume que un email siempre se 'envía'
                                            $data['agendar'] ?? false,
                                            isset($data['agenda']) ? Carbon::parse($data['agenda']) : null
                                        );
                                    })
                            ),

                        // Acción COMPLETA de CHAT
                        TextEntry::make('chats')
                            ->label('💬 Chats')
                            ->size('xl')
                            ->weight('bold')
                            ->alignment(Alignment::Center)
                            ->suffixAction(
                                ActionInfolist::make('add_chat')
                                    ->icon('icon-whatsapp') // Asegúrate de que este icono está disponible
                                    ->color('success')
                                    ->form([
                                        Textarea::make('comentario')
                                            ->label('Comentario (opcional)')
                                            ->rows(3)
                                            ->hint('Describe el chat realizado.')
                                            ->maxLength(500),
                                        Toggle::make('agendar')
                                            ->label('Agendar seguimiento')
                                            ->default(false)
                                            ->live(),
                                        DateTimePicker::make('agenda')
                                            ->label('Fecha de seguimiento')
                                            ->minutesStep(30)
                                            ->seconds(false)
                                            ->native(false)
                                            ->visible(fn (Forms\Get $get) => $get('agendar') === true)
                                            ->after(now()),
                                    ])
                                    ->action(function (array $data, \App\Models\Proyecto $record) {
                                        self::registrarInteraccion(
                                            $record,
                                            'chats',
                                            $data['comentario'] ?? '',
                                            true, // Se asume que un chat siempre se 'realiza'
                                            $data['agendar'] ?? false,
                                            isset($data['agenda']) ? Carbon::parse($data['agenda']) : null
                                        );
                                    })
                            ),

                        // Acción COMPLETA de OTROS
                        TextEntry::make('otros_acciones')
                            ->label('📎 Otros')
                            ->size('xl')
                            ->weight('bold')
                            ->alignment(Alignment::Center)
                            ->suffixAction(
                                ActionInfolist::make('add_otro')
                                    ->icon('heroicon-m-paper-airplane')
                                    ->color('gray')
                                    ->form([
                                        Textarea::make('comentario')
                                            ->label('Comentario obligatorio en esta acción')
                                            ->rows(3)
                                            ->required()
                                            ->hint('Describe la acción realizada.')
                                            ->maxLength(500),
                                        Toggle::make('agendar')
                                            ->label('Agendar seguimiento')
                                            ->default(false)
                                            ->live(),
                                        DateTimePicker::make('agenda')
                                            ->label('Fecha de seguimiento')
                                            ->minutesStep(30)
                                            ->seconds(false)
                                            ->native(false)
                                            ->visible(fn (Forms\Get $get) => $get('agendar') === true)
                                            ->after(now()),
                                    ])
                                    ->action(function (array $data, \App\Models\Proyecto $record) {
                                        self::registrarInteraccion(
                                            $record,
                                            'otros_acciones',
                                            $data['comentario'] ?? '',
                                            true, // Se asume que una 'otra acción' siempre se 'realiza'
                                            $data['agendar'] ?? false,
                                            isset($data['agenda']) ? Carbon::parse($data['agenda']) : null
                                        );
                                    })
                            ),
 // Totalizador de Interacciones
                        TextEntry::make('total_interacciones')
                            ->label('🔥 Total')
                            ->size('xl')
                            ->weight('extrabold')
                            ->color('warning')
                            ->alignment(Alignment::Center)
                            ->getStateUsing(fn (\App\Models\Proyecto $record) => $record->total_interacciones),
                        ])
                        ->columns(5)
                        ->columnSpan(3),
                ])
                ->columns(3)
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
              //  ->reverseItems()
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
                                        background-color: #e0f2fe;
                                        color: #1e3a8a;
                                        padding: 0.5rem 0.75rem;
                                        border-radius: 1rem;
                                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                                        margin: 0.5rem 0;
                                        font-size: 0.9rem;
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
                  RelationManagers\DocumentosRelationManager::class,

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

     public static function registrarInteraccion(
        \App\Models\Proyecto $record,
        string $tipo_accion,
        string $comentario_modal_texto,
        bool $contestada_o_enviado = false, // Para llamadas, email, chat
        bool $agendar_seguimiento = false,
        ?\Carbon\Carbon $agenda_fecha_modal = null
    ): void {
        $currentUser = Auth::user();
        $userName = $currentUser?->name ?? 'Usuario';

        // 1. Construir el texto inicial del comentario
        $comentarioTextoInicial = "";
        $notificacionTitulo = "";
        $notificacionBody = "";
        $notificacionTipo = "success"; // Por defecto

        switch ($tipo_accion) {
            case 'llamadas':
                $record->increment('llamadas');
                $notificacionTitulo = 'Llamada registrada';
                $comentarioTextoInicial = "Llamada registrada por {$userName}.";
                if ($contestada_o_enviado) { // Si es llamada 'contestada'
                    $comentarioTextoInicial .= " [Contestada]";
                    $notificacionBody = "Se ha registrado una llamada contestada.";
                } else { // Si es llamada 'sin respuesta'
                    $comentarioTextoInicial .= " [📞Sin respuesta]";
                    $notificacionBody = "Se ha registrado una llamada sin respuesta.";
                }
                break;
            case 'emails':
                $record->increment('emails');
                $notificacionTitulo = 'Email registrado';
                $comentarioTextoInicial = "📧 Email registrado por {$userName}.";
                $notificacionBody = "Se ha registrado el envío de un email.";
                break;
            case 'chats':
                $record->increment('chats');
                $notificacionTitulo = 'Chat registrado';
                $comentarioTextoInicial = "💬 Chat registrado por {$userName}.";
                $notificacionBody = "Se ha registrado una conversación por chat.";
                break;
            case 'otros_acciones':
                $record->increment('otros_acciones');
                $notificacionTitulo = 'Acción registrada';
                $comentarioTextoInicial = "📎 Otra acción registrada por {$userName}.";
                $notificacionBody = "Se ha registrado una acción general.";
                break;
        }

        // Añadir comentario del modal al texto inicial si existe
        if (!empty($comentario_modal_texto)) {
            $comentarioTextoInicial .= "\n---\nObservación: " . $comentario_modal_texto;
        }

        // 2. Actualizar la agenda y construir la parte final del comentario
        $comentarioTextoFinal = $comentarioTextoInicial;
        if ($agendar_seguimiento && $agenda_fecha_modal) {
            try {
                $record->agenda = $agenda_fecha_modal; // Actualiza el campo agenda del proyecto
                $record->save(); // Guarda el proyecto (con el contador incrementado y la agenda)

                $textoRelativo = $agenda_fecha_modal->diffForHumans();
                $fechaFormateada = $agenda_fecha_modal->isoFormat('dddd D [de] MMMM, HH:mm');
                $comentarioTextoFinal .= "\nPróximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
                $notificacionBody .= "\nPróximo seguimiento: " . $agenda_fecha_modal->format('d/m/Y H:i');
            } catch (\Exception $e) {
                Log::error('Error al procesar o guardar fecha de agenda en acción ' . $tipo_accion . ' para Proyecto ID ' . $record->id . ': ' . $e->getMessage());
                Notification::make()->title('Error al procesar fecha')->body('La fecha de agenda proporcionada no es válida o no se pudo guardar.')->danger()->send();
                $notificacionTipo = "warning"; // Notificación de error si falla la agenda
            }
        } else {
            // Si no se agendó, guardar el proyecto solo con el contador incrementado
            $record->save(); 
        }

        // 3. Crear el comentario polimórfico
        try {
            $record->comentarios()->create([
                'user_id' => $currentUser->id,
                'contenido' => $comentarioTextoFinal,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al guardar comentario de interacción para Proyecto ID ' . $record->id . ': ' . $e->getMessage());
            Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
            $notificacionTipo = "warning"; // Notificación de error si falla el comentario
        }

        // 4. Enviar Notificación final
        Notification::make()->title($notificacionTitulo)->body($notificacionBody)->{$notificacionTipo}()->send();
    }
}