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
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select; // Importa Select
use Filament\Forms\Components\TextInput; // Importa TextInput
use Filament\Forms\Components\Textarea; // Importa Textarea
use Filament\Forms\Components\DatePicker; // Importa DatePicker
use Filament\Forms\Components\DateTimePicker; // Importa DateTimePicker
use Filament\Forms\Components\Section; // Importa Section
use Filament\Tables\Columns\TextColumn; // Importa TextColumn
use App\Models\Cliente; // Para el filtro de cliente
use App\Models\User; // Para el filtro de usuario asignado
use App\Models\Servicio; // Para el filtro de servicio
use App\Models\Venta; // Para el filtro de venta
use App\Enums\ProyectoEstadoEnum; // Si usas el Enum para estados
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Components\Section as InfoSection;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Actions as InfolistActions; // <<< CAMBIO CLAVE: ESTE ES EL ALIAS CORRECTO
use Filament\Forms\Components\Select as FormsSelect; // Alias si hay conflicto, o solo Select si no hay
use Filament\Forms\Components\Textarea as FormsTextarea; // Alias si hay conflicto, o solo Textarea si no hay
use Filament\Forms\Components\DateTimePicker as FormsDateTimePicker; // Alias si hay conflicto, o solo DateTimePicker si no hay
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\Actions\Action;



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
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(), // Para ver detalles sin editar
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
                Grid::make(3)->schema([ // Grid principal para dividir la página en 3 columnas
                    // Columna 1: Información básica del Proyecto
                    InfoSection::make('Información General del Proyecto')
                        ->icon('heroicon-o-briefcase')
                        ->description('Detalles principales del proyecto y su origen.')
                        ->schema([
                            TextEntry::make('nombre')
                                ->label(new HtmlString('<span class="font-semibold">Nombre del Proyecto</span>'))
                                ->copyable()
                                ->weight('bold')
                                ->color('primary')
                                ->columnSpanFull(), // Ocupa todo el ancho en esta sección

                            TextEntry::make('cliente.razon_social')
                                ->label(new HtmlString('<span class="font-semibold">Cliente</span>'))
                                ->url(fn (Proyecto $record): ?string => 
                                    $record->cliente_id ? ClienteResource::getUrl('view', ['record' => $record->cliente_id]) : null
                                )
                                ->color(fn (Proyecto $record): string => $record->cliente_id ? 'primary' : 'secondary')
                                ->copyable()
                                ->weight('bold'),
                            
                            TextEntry::make('venta.id')
                                ->label(new HtmlString('<span class="font-semibold">Venta de Origen</span>'))
                                ->url(fn (Proyecto $record): ?string => 
                                    $record->venta_id ? VentaResource::getUrl('edit', ['record' => $record->venta_id]) : null
                                )
                                ->color(fn (Proyecto $record): string => $record->venta_id ? 'primary' : 'secondary')
                                ->copyable()
                                ->weight('bold')
                                ->placeholder('No asociada'),

                            TextEntry::make('servicio.nombre')
                                ->label(new HtmlString('<span class="font-semibold">Servicio Activador</span>'))
                                ->copyable()
                                ->weight('bold')
                                ->color('info'),
                            
                            TextEntry::make('ventaItem.id')
                                ->label(new HtmlString('<span class="font-semibold">ID Item Venta</span>'))
                                ->copyable()
                                ->weight('bold')
                                ->color('secondary')
                                ->placeholder('No asociado'),

                            TextEntry::make('descripcion')
                                ->label(new HtmlString('<span class="font-semibold">Descripción del Proyecto</span>'))
                                ->columnSpanFull()
                                ->markdown() // Si la descripción puede tener formato Markdown
                                ->placeholder('Sin descripción.'),
                        ])
                        ->columns(2) // 2 columnas dentro de esta sección
                        ->columnSpan(1), // Esta sección ocupa 1 de las 3 columnas principales

                    // Columna 2: Estado y Asignación
                    InfoSection::make('Estado & Gestión')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->description('Estado actual del proyecto y asesor asignado.')
                        ->schema([
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
                                ->suffixAction( // <<< CAMBIO AQUI: Sintaxis correcta para suffixAction
                                    Action::make('cambiar_estado_proyecto')
                                        ->label('') // No label, solo icono
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('primary')
                                        ->modalHeading('Cambiar Estado del Proyecto')
                                        ->modalSubmitActionLabel('Guardar Estado')
                                        ->modalWidth('md')
                                        ->form([
                                            FormsSelect::make('estado') // Usar FormsSelect
                                                ->label('Nuevo Estado')
                                                ->options(\App\Enums\ProyectoEstadoEnum::class)
                                                ->native(false)
                                                ->required()
                                                ->default(fn (?Proyecto $record): ?string => $record?->estado?->value),
                                            FormsTextarea::make('comentario_estado') // Usar FormsTextarea
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

                                            $record->estado = $nuevoEstado;
                                            if ($nuevoEstado === \App\Enums\ProyectoEstadoEnum::Finalizado && is_null($record->fecha_finalizacion)) {
                                                $record->fecha_finalizacion = now(); 
                                            }
                                            $record->save();

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
                                ), // Cierre de suffixAction()

                            TextEntry::make('user.name')
                                ->label(new HtmlString('<span class="font-semibold">Asesor Asignado</span>'))
                                ->badge()
                                ->getStateUsing(fn (Proyecto $record): string => $record->user?->name ?? '⚠️ Sin asignar')
                                ->color(fn (string $state): string => str_contains($state, 'Sin asignar') ? 'warning' : 'info'),
                            
                            TextEntry::make('fecha_inicio_estimada')
                                ->label(new HtmlString('<span class="font-semibold">Inicio Estimado</span>'))
                                ->date('d/m/Y')
                                ->placeholder('No establecido'),

                            TextEntry::make('fecha_fin_estimada')
                                ->label(new HtmlString('<span class="font-semibold">Fin Estimado</span>'))
                                ->date('d/m/Y')
                                ->placeholder('No establecido'),

                            TextEntry::make('fecha_finalizacion')
                                ->label(new HtmlString('<span class="font-semibold">Finalizado el</span>'))
                                ->dateTime('d/m/Y H:i')
                                ->placeholder('Aún no finalizado'),

                        ])
                        ->columns(2)
                        ->columnSpan(1), // Esta sección ocupa 1 de las 3 columnas principales

                    // Columna 3: Comentarios (Usando RepeatableEntry)
                    InfoSection::make('🗨️ Comentarios y Actividad')
                        ->icon('heroicon-o-chat-bubble-left')
                        ->description('Historial de comentarios y acciones sobre el proyecto.')
                        ->headerActions([ // Botón para añadir nuevo comentario
                            InfolistActions::make([ // InfolistActions::make() toma un array de Action::make()
                                Action::make('anadir_comentario')
                                    ->label('📝 Añadir comentario')
                                    ->icon('heroicon-o-plus-circle')
                                    ->color('warning')
                                    ->modalHeading('Nuevo Comentario del Proyecto')
                                    ->modalSubmitActionLabel('Guardar Comentario')
                                    ->form([
                                        FormsTextarea::make('contenido')
                                            ->label('Escribe el comentario')
                                            ->required()
                                            ->rows(4)
                                            ->placeholder('Escribe aquí tu comentario...'),
                                    ])
                                    ->action(function (array $data, Proyecto $record) {
                                        $record->comentarios()->create([
                                            'user_id' => auth()->id(),
                                            'contenido' => $data['contenido'],
                                        ]);
                                        Notification::make()->title('Comentario guardado')->success()->send();
                                    }), // Cierre de Action::make()
                            ]), // Cierre de InfolistActions::make([])
                        ])
                        ->schema([
                            TextEntry::make('comentarios_list') // Un TextEntry que usa la vista Blade personalizada
                                ->label(false)
                                ->view('filament.infolists.entries.custom-repeatable-comments') // Apunta a tu vista Blade
                                ->visible(fn (Proyecto $record) => $record->comentarios->isNotEmpty())
                                ->getStateUsing(fn (Proyecto $record) => $record->comentarios->sortByDesc('created_at')), // Pasar la colección de comentarios a la vista
                            
                        ])
                        ->columnSpan(1), // Esta sección ocupa 1 de las 3 columnas principales
                ]),
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