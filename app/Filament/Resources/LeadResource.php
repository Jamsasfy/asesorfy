<?php

namespace App\Filament\Resources;

use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\LeadResource\Pages;
use App\Filament\Resources\LeadResource\RelationManagers;
use App\Models\Comentario;
use App\Models\Lead;
use App\Models\MotivoDescarte;
use App\Models\Servicio;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;

use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\HtmlString;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\Actions\Action as ActionInfolist;
use Illuminate\Support\Facades\Log; // Para escribir en el log de Laravel
use Filament\Forms\Components\ViewField;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;




//use Filament\Tables\Actions\Action; // Para acciones personalizadas


class LeadResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'icon-leads';

    protected static ?string $navigationGroup = 'Gestión LEADS';

    //protected static ?string $navigationLabel = 'Todos los Leads';

    public static function getNavigationLabel(): string
{
    if (auth()->check() && auth()->user()->hasRole('comercial')) {
        return 'Mis Leads';
    }

    return 'Todos los Leads';
}


    protected static ?string $modelLabel = 'Lead';
    protected static ?string $pluralModelLabel = 'Todos los Leads';

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
            // Empieza con la consulta base del recurso
            $query = parent::getEloquentQuery()->with(['comentarios.user']);

            // Si el usuario es comercial, sólo ve sus leads
            if (auth()->user()->hasRole('comercial')) {
                $query->where('asignado_id', auth()->id());
            }

            return $query;

    }

public static function shouldRegisterNavigation(): bool
{
    // Solo los super_admins verán el recurso “Todos los Leads”
    return auth()->user()?->hasRole(['super_admin', 'comercial']);
}


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información de Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre Lead / Empresa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('tfn')
                            ->label('Teléfono')
                            ->required()
                            ->tel() // Validación básica de teléfono
                            // ->regex('/^(?:\+34|0034|34)?[6789]\d{8}$/') // Puedes mantener tu regex si prefieres
                            ->maxLength(20)
                            ->suffixIcon('heroicon-m-phone')
                            ->columnSpan(1),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->nullable() // Coincide con la migración
                            ->maxLength(255)
                             // Añadiremos validación única más compleja si es necesario,
                             // considerando leads y usuarios, al guardar.
                            ->suffixIcon('heroicon-m-envelope')
                            ->columnSpan(1),
                        
                    ]),

                Section::make('Origen y Asignación')
                    ->columns(2)
                    ->schema([
                        Select::make('procedencia_id')
                            ->relationship('procedencia', 'procedencia') 
                            ->label('Procedencia')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            
                            ->columnSpan(1),
                            Select::make('asignado_id')
                            ->relationship(
                                name: 'asignado', // Nombre de la relación en el modelo Lead
                                titleAttribute: 'name', // Atributo a mostrar del modelo User (ajusta si usas 'full_name' u otro)
                                // Modificador de la consulta para filtrar por rol:
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'comercial'))
                                                                                // Opcional: O añadir al propio usuario logueado aunque no sea comercial? ->orWhere('id', Auth::id())
                            )
                            ->label('Comercial Asignado')
                            ->searchable()
                            ->preload()
                            ->nullable() // Permite que el valor sea null (sin asignar)
                            ->placeholder('Sin Asignar') // Texto que se muestra si está vacío/null
                            // ->default(fn (): ?int => Auth::id()) // <-- Eliminamos esta línea, la lógica está ahora en mutateFormDataBeforeCreate
                            ->columnSpan(1),
                    ]),

                Section::make('Detalles y Estado')
                    ->columns(3) // Ajusta columnas según necesidad
                    ->schema([
                         Textarea::make('demandado')
                            ->label('Necesidad / Demanda del Lead')
                            ->nullable()
                            ->rows(4) // Más espacio que un TextInput
                            ->columnSpanFull(), // Ocupa todo el ancho

                            Select::make('estado')
                            ->options(LeadEstadoEnum::class)
                            ->required()
                            ->live()
                            ->default(LeadEstadoEnum::SIN_GESTIONAR)
                            ->label('Estado del Lead')
                            ->columnSpan(1)
                            ->disabled(fn (?Lead $record): bool =>
                            // Deshabilita si ya hay cliente y al menos una venta
                            ! is_null($record?->cliente_id) 
                        )
                        ->helperText(fn (?Lead $record): ?string =>
                            (! is_null($record?->cliente_id))
                                ? 'No puedes cambiar el estado, ya se creó el cliente y la venta del mismo.'
                                : null
                        )
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state, ?Lead $record, string $operation) {
                                $newEnum = $state instanceof LeadEstadoEnum ? $state : LeadEstadoEnum::tryFrom($state);
                        
                                // Lógica para fecha_gestion
                                $fechaGestionForm = $get('fecha_gestion');
                                $fechaGestionOriginal = $operation === 'edit' && $record ? $record->getOriginal('fecha_gestion') : null;
                        
                                if (
                                    $newEnum instanceof LeadEstadoEnum &&
                                    $newEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
                                    !$newEnum->isConvertido() &&
                                    $newEnum !== LeadEstadoEnum::DESCARTADO &&
                                    is_null($fechaGestionForm) &&
                                    is_null($fechaGestionOriginal)
                                ) {
                                    $set('fecha_gestion', now());
                                } elseif ($newEnum === LeadEstadoEnum::SIN_GESTIONAR) {
                                    $set('fecha_gestion', null);
                                }
                        
                                // Lógica para fecha_cierre
                                if ($newEnum?->isFinal()) {
                                    $set('fecha_cierre', now());
                                } else {
                                    $set('fecha_cierre', null);
                                }
                            }),
                        DateTimePicker::make('agenda')
                            ->label('Próximo Seguimiento')
                            ->native(false)
                            ->nullable()
                            ->required(function (Get $get): bool {
                                $state = $get('estado');
                                $estadoEnum = $state instanceof LeadEstadoEnum ? $state : LeadEstadoEnum::tryFrom($state);
                                return $estadoEnum instanceof LeadEstadoEnum &&
                                    $estadoEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
                                    !$estadoEnum->isConvertido() &&
                                    $estadoEnum !== LeadEstadoEnum::DESCARTADO;
                            })
                            ->visible(function (Get $get): bool {
                                $state = $get('estado');
                                $estadoEnum = $state instanceof LeadEstadoEnum ? $state : LeadEstadoEnum::tryFrom($state);
                                return $estadoEnum instanceof LeadEstadoEnum &&
                                    $estadoEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
                                    !$estadoEnum->isConvertido() &&
                                    $estadoEnum !== LeadEstadoEnum::DESCARTADO;
                            })
                            ->after(function (string $operation, Get $get, ?Lead $record): string|Carbon {
                                if ($operation === 'edit' && $record?->agenda) {
                                    return $record->agenda;
                                }
                                return now();
                            })
                            ->helperText('Obligatorio si el lead está en gestión. Debe ser una fecha futura.')
                            ->columnSpan(1),

                        DateTimePicker::make('fecha_gestion')
                            ->label('Inicio Gestión')
                            ->native(false)
                            ->readOnly()
                            ->nullable()
                            ->visible(function (Get $get): bool {
                                $state = $get('estado');
                                $estadoEnum = $state instanceof LeadEstadoEnum ? $state : LeadEstadoEnum::tryFrom($state);
                                return $estadoEnum instanceof LeadEstadoEnum &&
                                    $estadoEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
                                    !$estadoEnum->isConvertido() &&
                                    $estadoEnum !== LeadEstadoEnum::DESCARTADO;
                            })
                            ->helperText('Se actualiza automáticamente')
                            ->columnSpan(1),

                            DateTimePicker::make('fecha_cierre')
                            ->label('Fecha de Cierre')
                            ->native(false)
                            ->readOnly()
                            ->helperText('Se establece automáticamente cuando el lead se convierte o se descarta.')
                            ->visible(fn (Get $get) => filled($get('fecha_cierre')))
                            ->columnSpan(1),

                        Select::make('motivo_descarte_id')
                            ->label('Motivo de Descarte')
                            ->relationship('motivoDescarte', 'nombre', fn (Builder $query) => $query->where('activo', true))
                            ->searchable()
                            ->preload()
                            ->visible(function (Get $get): bool {
                                $estado = $get('estado');
                                $estadoEnum = $estado instanceof LeadEstadoEnum ? $estado : LeadEstadoEnum::tryFrom($estado);
                                return $estadoEnum === LeadEstadoEnum::DESCARTADO;
                            })
                            ->required(function (Get $get): bool {
                                $estado = $get('estado');
                                $estadoEnum = $estado instanceof LeadEstadoEnum ? $estado : LeadEstadoEnum::tryFrom($estado);
                                return $estadoEnum === LeadEstadoEnum::DESCARTADO;
                            })
                            ->columnSpan(1),

                        Textarea::make('observacion_cierre')
                             ->label('Observaciones de Cierre')
                             ->visible(function (Get $get): bool {
                                $state = $get('estado');
                                $estadoEnum = null;
                            
                                if ($state instanceof LeadEstadoEnum) {
                                    $estadoEnum = $state;
                                } elseif (is_string($state)) {
                                    $estadoEnum = LeadEstadoEnum::tryFrom($state);
                                }
                            
                                // Es visible solo si tenemos un Enum válido Y ese Enum es final
                                return !is_null($estadoEnum) && $estadoEnum->isFinal();
                            })
                             ->nullable()
                             ->rows(3)
                             ->columnSpanFull(), // Ocupa todo el ancho

                       
                       


                    ])


            ]);
    }

    

public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([

        Grid::make(3)->schema([
            // Info básica
            InfoSection::make('Información del Lead')
                ->schema([
                    TextEntry::make('nombre')
                        ->label(new HtmlString('<span class="text-xl font-semibold">👤 Nombre</span>'))                       
                        ->columnSpan(2),

                    TextEntry::make('tfn')
                        ->label(new HtmlString('<span class="text-xl font-semibold">📞 Teléfono</span>'))
                        ->copyable(),

                    TextEntry::make('email')
                    ->label(new HtmlString('<span class="text-xl font-semibold">✉️ Email</span>'))
                    ->copyable()
                    ->html() // Para que el <span> con clases funcione
                    ->getStateUsing(fn (Lead $record) => new HtmlString(
                        // break-all permite cortar en cualquier punto de la palabra
                        '<span class="whitespace-normal break-all">' . e($record->email) . '</span>'
                    ))
                    ->columnSpanFull(), // Ocupa todo el ancho de la sección

                    TextEntry::make('demandado')
                    ->label(new HtmlString('<span class="text-xl font-semibold">Demandado</span>'))
                        ->color('info')
                        ->copyable()                        
                       ->columnSpan(3),    
                ])
                ->columns(3)
                ->columnSpan(1),

            // Estado y asignación
            InfoSection::make('Estado & Asignación')
                ->schema([
                    TextEntry::make('creador.full_name')
                        ->badge()
                        ->color('gray')
                       
                        ->label(new HtmlString('<span class="text-lg font-semibold">🧑‍💻 Creado por</span>')),

                    TextEntry::make('created_at')
                        ->label(new HtmlString('<span class="font-semibold">🕒📅 Fecha de creación</span>'))
                        ->dateTime('d/m/y H:i'),
                    TextEntry::make('fecha_gestion')
                        ->label(new HtmlString('<span class="font-semibold">🔛📅 Comienzo gestión</span>'))
                        ->dateTime('d/m/y H:i'),    

                    TextEntry::make('asignado_display') 
                    ->label(new HtmlString('<span class="text-xl font-semibold">📌 Asignado a</span>'))
                        ->badge()
                        ->getStateUsing(function (Lead $record): string {
                            return $record->asignado?->full_name ?? '⚠️ Sin Asignar'; 
                        })
                        ->color(function (string $state): string {                           
                            return $state === '⚠️ Sin Asignar' ? 'warning' : 'info';
                        }),

                        TextEntry::make('estado')
                        ->label(new HtmlString('<span class="text-xl font-semibold">Estado Actual</span>'))
                        ->badge()
                        ->color(fn (?LeadEstadoEnum $state): string => match ($state) { // Usa $state que es Enum
                            LeadEstadoEnum::SIN_GESTIONAR => 'gray',
                            LeadEstadoEnum::INTENTO_CONTACTO => 'warning',
                            LeadEstadoEnum::CONTACTADO => 'info',
                            LeadEstadoEnum::ANALISIS_NECESIDADES => 'primary',
                            LeadEstadoEnum::ESPERANDO_INFORMACION => 'warning',
                            LeadEstadoEnum::PROPUESTA_ENVIADA => 'info',
                            LeadEstadoEnum::EN_NEGOCIACION => 'primary',
                            LeadEstadoEnum::CONVERTIDO           => 'success',  // <— unificado aquí
                            LeadEstadoEnum::DESCARTADO => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (?LeadEstadoEnum $state): string => $state?->getLabel() ?? 'Desconocido') // Usa $state que es Enum
                        ->suffixAction(
                            ActionInfolist::make('cambiar_estado')
                                ->label('') // Solo icono
                                ->icon('heroicon-m-arrow-path')
                                ->color('primary')
                                ->tooltip(fn (Lead $record): string =>
                                        is_null($record->cliente_id)
                                            ? 'Cambiar estado'
                                            : 'Imposible: ya dispone de cliente y venta'
                                    )
                                ->modalHeading(fn(?Lead $record): string => "Cambiar Estado de " . ($record?->nombre ?? 'este lead'))
                                ->modalSubmitActionLabel('Guardar Estado')
                                ->modalWidth('xl') // O el ancho que prefieras
                                                    // Solo muéstralo si NO hay cliente asignado aun
                                                    ->visible(fn (Lead $record): bool =>
                                                    // Sólo muestra mientras no exista ninguna venta ligada a este Lead
                                                    $record->ventas()->count() === 0
                                                )
                                ->form([ // Formulario del modal CORREGIDO
                                    Select::make('estado')
                                        ->label('Nuevo estado')
                                        ->options(LeadEstadoEnum::class) // Usa Enum directo (si tiene HasLabel)
                                        ->required()
                                        ->live() // Necesario para campos condicionales
                                        ->default(fn (?Lead $record): ?string => $record?->estado?->value) // Default es estado actual
                                        ->columnSpanFull(),
                    
                                      // ——— Mensaje que solo sale si eliges Convertido ———
                                      Placeholder::make('info_convertido')
                                      ->label(false)
                                      ->content(new HtmlString('
                                          <div style="
                                              background-color: #fef9c3;
                                              color: #92400e;
                                              padding: 0.75rem;
                                              border-radius: 0.375rem;
                                              margin-bottom: 1rem;
                                              font-weight: bold;
                                              font-size: 0.95rem;
                                          ">
                                              🔔 Atención: tras guardar como <span style="text-decoration: underline;">Convertido</span>,
                                              serás redirigido al formulario para crear el Cliente; sin ello no podrás generar Ventas.
                                          </div>
                                      '))
                                      ->visible(fn (Get $get): bool =>
                                          LeadEstadoEnum::tryFrom($get('estado') ?? '') === LeadEstadoEnum::CONVERTIDO
                                      )
                                      ->columnSpanFull(),

                                    Select::make('motivo_descarte_id')
                                        ->label('Motivo de Descarte')
                                        ->relationship('motivoDescarte', 'nombre', fn (Builder $query) => $query->where('activo', true)) // Asume 'nombre' en MotivoDescarte
                                        ->searchable()->preload()->nullable()
                                        ->default(fn (?Lead $record): ?int => $record?->motivo_descarte_id)
                                        ->visible(function (Get $get): bool { $state = $get('estado'); $enum = LeadEstadoEnum::tryFrom($state ?? ''); return $enum === LeadEstadoEnum::DESCARTADO; }) // Visible si DESCARTADO
                                        ->required(function (Get $get): bool { $state = $get('estado'); $enum = LeadEstadoEnum::tryFrom($state ?? ''); return $enum === LeadEstadoEnum::DESCARTADO; }) // Requerido si DESCARTADO
                                        ->columnSpanFull(),
                    
                                    DateTimePicker::make('fecha_cierre')
                                        ->label('Fecha de Cierre') ->native(false)->default(now())
                                        ->visible(function (Get $get): bool { $state = $get('estado'); $enum = LeadEstadoEnum::tryFrom($state ?? ''); return !is_null($enum) && $enum->isFinal(); }) // Visible si FINAL
                                        ->required(function (Get $get): bool { $state = $get('estado'); $enum = LeadEstadoEnum::tryFrom($state ?? ''); return !is_null($enum) && $enum->isFinal(); }) // Requerido si FINAL
                                        ->columnSpanFull(),
                    
                                    Textarea::make('observacion_cierre')
                                        ->label('Observaciones del Cierre') ->rows(4)->maxLength(1000)
                                        ->default(fn (?Lead $record): ?string => $record?->observacion_cierre)
                                        ->visible(function (Get $get): bool { $state = $get('estado'); $enum = LeadEstadoEnum::tryFrom($state ?? ''); return !is_null($enum) && $enum->isFinal(); }) // Visible si FINAL
                                        ->required(function (Get $get): bool { $state = $get('estado'); $enum = LeadEstadoEnum::tryFrom($state ?? ''); return $enum === LeadEstadoEnum::DESCARTADO; }) // Requerido SOLO si DESCARTADO
                                        ->helperText(fn(Get $get) => (LeadEstadoEnum::tryFrom($get('estado') ?? '') === LeadEstadoEnum::DESCARTADO) ? 'Obligatorio al descartar.' : 'Opcional si convierte.')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, Lead $record) { // Lógica de acción CORREGIDA
                                    $nuevoEstado = LeadEstadoEnum::tryFrom($data['estado']);
                                    if (!$nuevoEstado) { Notification::make()->danger()->title('Error Estado')->send(); return; }
                    
                                    $record->estado = $nuevoEstado; // Asigna el objeto Enum
                                    $comentarioBase = 'Cambio de estado a: ' . $nuevoEstado->getLabel();
                                    $record->motivo_descarte_id = null;
                                    $record->observacion_cierre = null; // Limpiar antes de reasignar si aplica
                    
                                    // Lógica para estados finales
                                    if ($nuevoEstado->isFinal()) {
                                        $record->fecha_cierre = $data['fecha_cierre'] ?? now();
                                        if (!empty($data['observacion_cierre'])) { $record->observacion_cierre = $data['observacion_cierre']; }
                                        if ($nuevoEstado === LeadEstadoEnum::DESCARTADO) {
                                            if (!empty($data['motivo_descarte_id'])) {
                                                $record->motivo_descarte_id = $data['motivo_descarte_id'];
                                                $motivo = MotivoDescarte::find($data['motivo_descarte_id']);
                                                if ($motivo) { $comentarioBase .= ' - Motivo: ' . $motivo->nombre; }
                                            }
                                             // Añadir observación obligatoria al comentario si es descarte
                                             if (!empty($data['observacion_cierre'])) { $comentarioBase .= "\n---\nObservación: " . $data['observacion_cierre']; }
                                        }
                                    } else { // Si no es estado final, limpiar campos de cierre
                                        $record->fecha_cierre = null;
                                        $record->motivo_descarte_id = null;
                                        $record->observacion_cierre = null;
                                    }
                    
                                   
                    
                                    $comentarioFinal = $comentarioBase; // Usamos el texto construido
                    
                                    $record->save(); // Guardar Lead
                    
                                    // --- Crear comentario usando SAVE manual ---
                                    try {
                                        $comentario = new Comentario();
                                        $comentario->user_id = Auth::id();
                                        $comentario->contenido = $comentarioFinal; // Usa campo 'contenido'
                                        $record->comentarios()->save($comentario); // Usa método save()
                                    } catch (\Exception $e) { 
                                        Log::error('Error al guardar comentario (cambiar_estado): ' . $e->getMessage()); 
                                        Notification::make()->danger()->title('Error Comentario')->send(); }
                                    // --- Fin crear comentario ---
                    
                                    Notification::make()->title('Estado actualizado')->success()->send();

                                    if ($nuevoEstado->isConvertido()) {
                                        // Si ya existe cliente vinculado, vamos a Crear Venta
                                        if ($record->cliente_id) {
                                            return redirect(
                                                \App\Filament\Resources\VentaResource::getUrl('create', [
                                                    'cliente_id' => $record->cliente_id,
                                                    'lead_id'    => $record->id,
                                                ])
                                            );
                                        }
                                    
                                        // Si no existe cliente aún, vamos a Crear Cliente
                                        return redirect(
                                            \App\Filament\Resources\ClienteResource::getUrl('create', [
                                                'lead_id'      => $record->id,
                                                'razon_social' => $record->nombre,
                                                'email'        => $record->email,
                                                'telefono'     => $record->tfn,
                                                'next'         => 'sale',
                                            ])
                                        );
                                    }

                                }) // Fin ->action()
                        ), // Fin ->suffixAction()

                        TextEntry::make('venta_asociada')
                        ->label(new HtmlString('<span class="text-xl font-semibold">Venta Asociada</span>'))
                        ->getStateUsing(fn (Lead $record): string =>
                            '🔗 Ver venta #' . $record->ventas->first()->id
                        )
                        ->visible(fn (Lead $record): bool =>
                            $record->ventas->isNotEmpty()
                        )
                        ->url(fn (Lead $record): string =>
                        VentaResource::getUrl('edit', ['record' => $record->ventas->first()->id])
                    )
                        ->openUrlInNewTab()
                        ->badge()
                        ->color('warning'),
                                    ])
                
                ->columns(3)
                ->columnSpan(1),

            // Agenda
            InfoSection::make('Agenda & Gestión')
                ->schema([
                    TextEntry::make('updated_at')
                    ->label(new HtmlString('<span class="font-semibold">🔄📅 Lead Actualizado</span>'))
                        ->dateTime('d/m/y H:i'),

                        TextEntry::make('agenda')
                        ->label(new HtmlString('<span class="font-semibold">📆 Próxima cita</span>'))
                        ->dateTime('d/m/y H:i')
                        ->suffixAction(
                            ActionInfolist::make('reagendar')
                                ->icon('heroicon-m-calendar-days')
                                ->form([
                                    DateTimePicker::make('agenda') // <- CAMBIA AQUÍ
                                        ->label('Nueva fecha de agenda')
                                        ->displayFormat('d/m/Y H:i') // Mostramos fecha y hora
                                        ->native(false)
                                        ->default(fn (Lead $record) => $record->agenda ?? now())
                                        ->minutesStep(30), // Intervalo de 30 minutos
                                ])
                                ->action(function (array $data, Lead $record) {
                                    $record->agenda = $data['agenda'];
                                    $record->save();

                                    $fechaFormateada = \Carbon\Carbon::parse($data['agenda'])->format('d/m/Y H:i');

                                    $record->comentarios()->create([
                                        'user_id' => auth()->id(),
                                        'contenido' => '📅 Nueva agenda fijada para: ' . $fechaFormateada,
                                    ]);

                                    Notification::make()
                                        ->title('✅ Agenda actualizada')
                                        ->body('Se ha registrado la nueva fecha de agenda correctamente.')
                                        ->success()
                                        ->send();
                                })
                               
                        ),
                       

                        // --- Fecha de cierre ---
                        InfoSection::make('Interacciones')
                        ->schema([
                           
                            // Acción COMPLETA de LLAMADA ✔️
                            TextEntry::make('llamadas')
                                ->label('📞 Llamadas')
                                ->size('xl')
                                ->weight('bold')
                                ->alignment(Alignment::Center)
                                ->suffixAction(
                                    ActionInfolist::make('add_llamada')
                                        ->icon('heroicon-m-phone-arrow-up-right')
                                        ->color('primary')
                                        ->form([
                                            Toggle::make('respuesta')
                                                ->label('Contestado')
                                                ->default(false)
                                                ->helperText('Marca si el lead ha contestado la llamada.')
                                                ->live(),
                            
                                            Textarea::make('comentario')
                                                ->label('Comentario')
                                                ->rows(3)
                                                ->hint('Describe brevemente la llamada.')
                                                ->visible(fn (Get $get) => $get('respuesta') === true)
                                                ->required(fn (Get $get) => $get('respuesta') === true)
                                                ->maxLength(500),
                            
                                            Toggle::make('agendar')
                                                ->label('Agendar nueva llamada')
                                                ->default(false)
                                                ->helperText('Programa una nueva cita de seguimiento.')
                                                ->live(),
                            
                                            DateTimePicker::make('agenda')
                                                ->label('Fecha y hora de la nueva llamada')
                                                ->minutesStep(30)
                                                ->seconds(false)
                                                ->native(false)
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar llamada')
                                        ->modalSubmitActionLabel('Registrar llamada')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $currentUser = Auth::user();
                                            $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name' o 'full_name' según tu modelo User
                    
                                            // 1. Incrementar contador
                                            $record->increment('llamadas');
                    
                                            // 2. Construir el texto INICIAL del comentario (sin la info de agenda todavía)
                                            $comentarioTextoInicial = "Llamada registrada por {$userName}.";
                                            if (isset($data['respuesta']) && $data['respuesta'] === true) {
                                                $comentarioTextoInicial .= " [Contestada]";
                                                if (!empty($data['comentario'])) {
                                                    $comentarioTextoInicial .= " - Observación: " . $data['comentario'];
                                                }
                                            } else {
                                                $comentarioTextoInicial .= " [📞Sin respuesta]";
                                            }
                    
                                            // 3. Actualizar la agenda SOLO si el toggle 'agendar' estaba marcado y hay una fecha VÁLIDA
                                            $agendaActualizada = false;
                                            $nuevaAgendaEstablecida = false; // Bandera para saber si se AGENDÓ algo en este paso
                    
                                            // *** LÓGICA DE VERIFICACIÓN CON NOMBRE DE CAMPO 'agenda' Y isset ANIDADO ***
                                            // Primero, verificamos si el toggle 'agendar' está marcado en los datos recibidos
                                            if (isset($data['agendar']) && $data['agendar'] === true) {
                                                // Si el toggle está marcado, ahora verificamos si la clave 'agenda' existe Y está llena
                                                if (isset($data['agenda']) && filled($data['agenda'])) { // <-- Verificamos 'agenda' y que esté filled
                                                     try {
                                                        // Si existe y está llena, intentamos parsear y guardar. Ahora es seguro acceder a $data['agenda'].
                                                        $nuevaFechaAgenda = Carbon::parse($data['agenda']); // <-- Usamos $data['agenda']
                                                        $record->agenda = $nuevaFechaAgenda;
                                                        $record->save(); // Guardamos el lead
                                                        $agendaActualizada = true;
                                                        $nuevaAgendaEstablecida = true; // Se estableció una nueva agenda en esta interacción
                    
                                                     } catch (\Exception $e) {
                                                        // Capturamos errores de parsing o de guardado
                                                        Log::error('Error al procesar o guardar fecha de agenda en acción Llamada para Lead ID '.$record->id.': '.$e->getMessage());
                                                        Notification::make()->title('Error al procesar fecha')->body('La fecha de agenda proporcionada no es válida o no se pudo guardar.')->danger()->send();
                                                        $agendaActualizada = false;
                                                        $nuevaAgendaEstablecida = false;
                                                     }
                                                } else {
                                                     // Log opcional si el toggle está ON pero el campo de fecha está vacío (la validación debería evitar esto)
                                                     Log::warning('Llamada action: Toggle agendar ON pero campo agenda vacio/no existe para Lead ID '.$record->id);
                                                }
                                            }
                                            // Si el toggle 'agendar' no estaba marcado, las banderas $agendaActualizada y $nuevaAgendaEstablecida
                                            // permanecen en false, que es el comportamiento deseado.
                                            // *** FIN LÓGICA DE VERIFICACIÓN ***
                    
                    
                                            // --- 4. Construimos el texto FINAL del comentario (añadiendo info de agenda SOLO SI SE AGENDA) ---
                                            $comentarioTextoFinal = $comentarioTextoInicial; // Empezamos con el texto base
                    
                                            // Añadimos información sobre la agenda SOLO si se estableció una nueva fecha en esta interacción
                                            if ($nuevaAgendaEstablecida) { // <-- Usamos la bandera, que se puso a true solo si se agendó exitosamente
                                                $comentarioTextoFinal .= "\n---"; // Separador
                    
                                                 // Ahora $record->agenda ya tiene la fecha actualizada si el paso 3 tuvo éxito
                                                 if ($record->agenda instanceof Carbon) { // Verificamos si ahora hay una fecha de agenda válida en el lead
                                                     $textoRelativo = $record->agenda->diffForHumans();
                                                     $fechaFormateada = $record->agenda->isoFormat('dddd D [de] MMMM, HH:mm'); // <-- Formato HH:mm correcto
                                                     $comentarioTextoFinal .= "\nPróximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
                                                 }
                                                 // Si $nuevaAgendaEstablecida es true pero $record->agenda no es Carbon (un caso de error),
                                                 // no añadimos texto de agenda aquí.
                                            }
                                            // Si $nuevaAgendaEstablecida es false, simplemente no añadimos nada sobre la agenda.
                                            // --- Fin construcción comentario final ---
                    
                    
                                            // 5. Crear el comentario polimórfico
                                            try {
                                                $record->comentarios()->create([
                                                    'user_id' => $currentUser->id,
                                                    'contenido' => $comentarioTextoFinal // Usamos el texto FINAL
                                                ]);
                                            } catch (\Exception $e) {
                                                 Log::error('Error al guardar comentario (acción Llamada): ' . $e->getMessage());
                                                Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
                                            }
                    
                                            // 6. Enviar Notificación final
                                             Notification::make()->success()->title('Llamada registrada')->send();
                                             if ($agendaActualizada) {
                                                 Notification::make()->title('Agenda actualizada')->body('El próximo seguimiento ha sido modificado.')->info()->send();
                                             }
                    
                                          
                                        }),
                                        
                                ),
                            
                            
                            // Acción COMPLETA de EMAIL ✔️
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
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar Email')
                                        ->modalSubmitActionLabel('Registrar Email')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $comentarioTexto = '📧 Email enviado: ' . ($data['comentario'] ?? 'Sin comentario');
                                            $agenda = isset($data['agenda']) ? Carbon::parse($data['agenda']) : null;
                            
                                            LeadResource::registrarInteraccion($record, 'emails', $comentarioTexto, $agenda);
                                        })
                                ),
                            
                            
                            // Acción COMPLETA de CHAT ✔️
                            TextEntry::make('chats')
                                ->label('💬 Chats')
                                ->size('xl')
                                ->weight('bold')
                                ->alignment(Alignment::Center)
                                ->suffixAction(
                                    ActionInfolist::make('add_chat')
                                        ->icon('icon-whatsapp')
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
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar Chat')
                                        ->modalSubmitActionLabel('Registrar Chat')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $comentarioTexto = '💬 Chat enviado: ' . ($data['comentario'] ?? 'Sin comentario.');
                                            $agenda = isset($data['agenda']) ? Carbon::parse($data['agenda']) : null;
                            
                                            LeadResource::registrarInteraccion($record, 'chats', $comentarioTexto, $agenda);
                                        })
                                ),
                            
                            
                            // Acción COMPLETA de OTROS ✔️
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
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar Otra Acción')
                                        ->modalSubmitActionLabel('Registrar Acción')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $comentarioTexto = '📎 Otra acción realizada: ' . ($data['comentario'] ?? 'Sin comentario.');
                                            $agenda = isset($data['agenda']) ? Carbon::parse($data['agenda']) : null;
                            
                                            LeadResource::registrarInteraccion($record, 'otros_acciones', $comentarioTexto, $agenda);
                                        })
                                    ),
                            
                            
                            // Recuerda tener definido el método registrarInteraccion correctamente.
                            
                        

                    TextEntry::make('total')
                        ->label('🔥 Total')
                        ->state(fn (Lead $record) => $record->llamadas + $record->emails + $record->chats + $record->otros_acciones)
                        ->size('xl')
                        ->weight('extrabold')
                        ->color('warning')
                        ->alignment(Alignment::Center),
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
                ->action(function (array $data, Lead $record) {
                    $record->comentarios()->create([
                        'user_id' => auth()->id(),
                        'contenido' => $data['contenido'],
                    ]);
        
                    Notification::make()
                        ->title('Comentario guardado')
                        ->success()
                        ->send();
                }),

                ActionInfolist::make('crear_cliente')
                ->label('👤 Crear Cliente')
                ->icon('heroicon-m-user-plus')
                ->color('primary')
                ->visible(fn (Lead $record) => $record->estado === LeadEstadoEnum::CONVERTIDO->value && ! $record->cliente)
                ->url(fn (Lead $record) => ClienteResource::getUrl('create', [
                    // Pasamos todos los datos del lead que queremos pre-llenar
                    'razon_social' => $record->nombre,
                    'email'        => $record->email,
                    'telefono'     => $record->tfn,
                    'lead_id'      => $record->id,
                    'comercial_id' => auth()->id(),
                ])),


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
                ->visible(fn (Lead $record) => $record->comentarios->isNotEmpty()),
        ])
    ]);
}

      

// Método helper COMPLETO Y CORREGIDO para añadir info de agenda al comentario SOLO SI SE AGENDA

protected static function registrarInteraccion(Lead $record, string $campoContador, string $comentarioTextoInicial, ?Carbon $agenda = null): void
{
    // 1. Incrementamos el contador
    try {
        $record->increment($campoContador);
    } catch (\Exception $e) {
        Log::error("Error al incrementar contador '{$campoContador}' para Lead ID {$record->id}: " . $e->getMessage());
        Notification::make()->title('Error Interno')->body('No se pudo registrar la interacción.')->danger()->send();
        return;
    }

    // 2. Si nos pasan una nueva fecha de agenda, la actualizamos
    $agendaActualizada = false;
    $nuevaAgendaEstablecida = false; // Bandera para saber si se AGENDÓ algo en este paso
    if ($agenda) { // <-- Comprobamos si se PASÓ una fecha de agenda a este método
        try {
             $record->agenda = $agenda; // Usamos el objeto Carbon directamente
             $record->save(); // Guardamos el Lead para actualizar la agenda en la BD y en el objeto $record
             $agendaActualizada = true;
             $nuevaAgendaEstablecida = true; // Se estableció una nueva agenda en esta interacción
        } catch (\Exception $e) {
            Log::error('Error al actualizar fecha de agenda en registrarInteraccion para Lead ID '.$record->id.': '.$e->getMessage());
            Notification::make()->title('Error')->body('Fecha de agenda proporcionada no válida.')->danger()->send();
            // Continuamos, pero sin marcar como agendado si hubo error
            $agendaActualizada = false;
            $nuevaAgendaEstablecida = false;
        }
    }

    // --- 3. Construimos el texto FINAL del comentario (añadiendo info de agenda SOLO SI SE AGENDA) ---
    $comentarioTextoFinal = $comentarioTextoInicial; // Empezamos con el texto base de la acción

    // Añadimos información sobre la agenda SOLO si se estableció una nueva fecha en esta interacción
    if ($nuevaAgendaEstablecida) { // <-- Usamos la bandera
        $comentarioTextoFinal .= "\n---"; // Separador

         // Ahora $record->agenda ya tiene la fecha actualizada si el paso 2 tuvo éxito
         if ($record->agenda instanceof Carbon) { // Verificamos si ahora hay una fecha de agenda válida en el lead
             $textoRelativo = $record->agenda->diffForHumans();
             $fechaFormateada = $record->agenda->isoFormat('dddd D [de] MMMM, [a las] HH:mm');
             $comentarioTextoFinal .= "\nPróximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
         }
         // Si $nuevaAgendaEstablecida es true pero $record->agenda no es Carbon, es un caso de error ya notificado.
         // No añadimos texto de agenda en este caso.
    }
    // Si $nuevaAgendaEstablecida es false, simplemente no añadimos nada sobre la agenda.

    // --- 4. Creamos el comentario usando el texto final ---
    try {
        $comentario = new Comentario();
        $comentario->user_id = Auth::id();
        $comentario->contenido = $comentarioTextoFinal; // Usamos el texto FINAL
        $record->comentarios()->save($comentario);
    } catch (\Exception $e) {
        Log::error('Error al guardar comentario (helper): ' . $e->getMessage(), [
            'lead_id' => $record->id, 'user_id' => Auth::id(), 'contenido_length' => strlen($comentarioTextoFinal ?? '')
        ]);
        Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
    }
    // --- Fin creación comentario ---

    // 5. Enviar Notificación final (Opcional, si no lo manejas en cada acción)
    // Las notificaciones de éxito/error de la agenda se manejan en el paso 2
    // La notificación principal de interacción registrada se puede hacer aquí o en la acción

    // Intentar refrescar el infolist (puede que necesites ajustar esto)
    // $this->infolist; // Esto no va aquí, va en la acción Livewire
}


    public static function table(Table $table): Table
    {
       
        return $table
        ->paginated([25, 50, 100, 'all']) // Ajusta opciones si quieres
        ->striped()
        ->recordUrl(null)    // Esto quita la navegación al hacer clic en la fila

        ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->columns([
            // Columna Total Interacciones (Adaptada)
            TextColumn::make('total_interactions')
                ->label('Acciones') // Etiqueta corta
                ->tooltip('Total Interacciones (Llamadas + Emails + Chats + Otras)')
                ->state(function (Lead $record): int {
                     // Suma los contadores
                     return $record->llamadas + $record->emails + $record->chats + $record->otros_acciones;
                })
                ->numeric()
                ->size('2xl')
                ->weight('extrabold')
                ->color('warning')
                ->alignment(Alignment::Center),
            TextColumn::make('creador.full_name')
                ->label('Creado por')
                ->sortable()
                ->badge() 
                ->color('gray'),

            TextColumn::make('procedencia.procedencia')
                ->label('Procedencia')
                ->badge()      
                ->sortable()
                ->searchable(),


            // Datos del Lead
            TextColumn::make('nombre')
             ->searchable(isIndividual: true)
                //->copyable()
                //->copyMessage('Nombre Copiado')
                 // Si existe cliente asociado, convierte el nombre en enlace a su ficha
                ->url(fn (Lead $record): ?string => 
                $record->cliente_id
                    ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                    : null
                )
                // Color amarillo (warning) si es enlace, gris si no
                ->color(fn (Lead $record): ?string =>
                    $record->cliente_id
                        ? 'warning'
                        : null
                )
                // Abre en pestaña nueva solo cuando haya URL
                ->openUrlInNewTab(),
                
            TextColumn::make('email')
                ->searchable(isIndividual: true)
                ->copyable()
                ->copyMessage('Email Copiado'),
              
            TextColumn::make('tfn')
                ->label('Teléfono')
                ->searchable(isIndividual: true)
                ->copyable()
                ->copyMessage('Teléfono Copiado')
                ->icon('heroicon-m-phone'),

            // Estado (Adaptado con Enum)
            TextColumn::make('estado')
                ->badge()
                ->formatStateUsing(fn (?LeadEstadoEnum $state): string => $state?->getLabel() ?? '-')
                ->color(fn (?LeadEstadoEnum $state): string => match ($state) {
                    LeadEstadoEnum::SIN_GESTIONAR => 'gray',
                    LeadEstadoEnum::INTENTO_CONTACTO => 'warning',
                    LeadEstadoEnum::CONTACTADO => 'info',
                    LeadEstadoEnum::ANALISIS_NECESIDADES => 'primary',
                    LeadEstadoEnum::ESPERANDO_INFORMACION => 'warning',
                    LeadEstadoEnum::PROPUESTA_ENVIADA => 'info',
                    LeadEstadoEnum::EN_NEGOCIACION => 'primary',
                    LeadEstadoEnum::CONVERTIDO => 'success',
                    LeadEstadoEnum::DESCARTADO => 'danger',
                    default => 'gray'
                })
                ->searchable() // Buscar por el valor string del estado
                ->sortable(),

            // Asignado y Procedencia
             // --- COLUMNA ASIGNADO (VERSIÓN DEBUG) ---
             TextColumn::make('asignado_display') // Usamos un nombre diferente para evitar conflictos con la relación
             ->label('Comercial asignado')
             ->badge() 
             ->getStateUsing(function (Lead $record): string {
                 // ***** ¡¡IMPORTANTE!! Cambia 'name' si tu atributo en User es 'full_name' u otro *****
                 return $record->asignado // Comprueba si la relación está cargada (si hay un usuario asignado)
                     ? $record->asignado->name // Si sí, devuelve el nombre
                     : '⚠️ Sin Asignar'; // Si no, devuelve el texto fijo (con emoji si quieres)
             })
             ->color(fn ($state) => str_contains($state, 'Sin Asignar') ? 'warning' : 'info')
             ->searchable(/* 
                query: function (Builder $query, string $search): Builder {
                    // Le decimos que busque Leads DONDE la relación 'asignado' EXISTA Y CUMPLA una condición:
                    // Que el campo 'name' (¡o 'full_name'!) de ese usuario asignado contenga el texto buscado.
                    // ***** ¡¡IMPORTANTE!! Cambia 'name' aquí si tu atributo en User es otro *****
                    return $query->orWhereHas('asignado', function (Builder $q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                },
                isIndividual: true // Mantenemos la búsqueda individual para esta columna */
            )
            // --- FIN CORRECCIÓN ---
             ->sortable(['asignado.name'])         
             ->toggleable(isToggledHiddenByDefault: false),

           

            // Fechas Clave
             TextColumn::make('agenda')
                ->label('Agendado')
                ->dateTime('d/m/y H:i') // Quitar segundos si no son necesarios
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('fecha_gestion') // Campo renombrado
                 ->label('Gestionado el lead')
                 ->dateTime('d/m/y H:i')
                 ->sortable()
                 ->toggleable(isToggledHiddenByDefault: true),

             TextColumn::make('updated_at')
                ->label('Actualizado el lead')
                ->since() // Mostrar relativo (ej: 'hace 5 minutos')
                //->dateTime('d/m/y H:i') // O formato fijo
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

             TextColumn::make('created_at')
                ->label('Creado en app')
                ->dateTime('d/m/y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto

            // Creador (Opcional)
           

        ])
        ->filters([
             // Filtros adaptados
             SelectFilter::make('estado')
                 ->options(LeadEstadoEnum::class) // Usa el Enum (asegúrate que Enum tiene HasLabel)
                 ->multiple()
                 ->label('Estado del Lead'),

             SelectFilter::make('asignado_id')
                 ->label('Comercial Asignado')
                 ->relationship(
                     'asignado',
                     'name', // Ajusta a 'full_name' si es necesario
                     fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'comercial'))
                 )
                 ->searchable()
                 ->preload()
                 ->multiple(),

             SelectFilter::make('procedencia_id')
                ->label('Procedencia del Lead')
                ->relationship('procedencia', 'procedencia') // Usa 'procedencia'
                ->multiple()
                ->preload()
                ->searchable(),

             DateRangeFilter::make('created_at')
                ->label('Fecha Creación'),

             DateRangeFilter::make('agenda')
                ->label('Fecha Agendada')
                ->ranges([
                    // --- PASADO ---
                    'Ayer' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                    'Semana Pasada' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
                    'Mes Pasado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                    'Año Pasado' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            
                    // --- PRESENTE ---
                    'Hoy' => [now()->startOfDay(), now()->endOfDay()],
            
                    // --- PERIODOS ACTUALES (Incluyen presente y futuro cercano) ---
                    'Esta Semana' => [now()->startOfWeek(), now()->endOfWeek()],
                    'Este Mes' => [now()->startOfMonth(), now()->endOfMonth()],
                    'Este Año' => [now()->startOfYear(), now()->endOfYear()],
            
                    // --- FUTURO ---
                    'Próxima Semana' => [now()->addWeek()->startOfWeek(), now()->addWeek()->endOfWeek()],
                    'Próximo Mes' => [now()->addMonthNoOverflow()->startOfMonth(), now()->addMonthNoOverflow()->endOfMonth()],
                    'Próximo Año' => [now()->addYear()->startOfYear(), now()->addYear()->endOfYear()],
                ]),
                // ->ranges([...]) // Puedes mantener tus rangos predefinidos

        ], layout: FiltersLayout::AboveContent) // Mantener layout
        ->filtersFormColumns(5) // Mantener columnas

        ->actions([ // Acciones de Fila
            Tables\Actions\ViewAction::make()
                ->label('') // Sin etiqueta, solo icono
                ->openUrlInNewTab() // Abrir en nueva pestaña
                ->tooltip('Ver Detalles'), // Texto al pasar el ratón

            Tables\Actions\EditAction::make()
                 ->label('')
                 ->tooltip('Editar Lead'),
            Tables\Actions\Action::make('llamar')
                 ->icon('heroicon-o-phone-arrow-up-right')
                 ->label('')
                 ->tooltip('Registrar Llamada y Opcionalmente Reagendar')
                 ->color('primary')
                 // --- Usamos form() directamente, sin Wizard ---
                 ->form([
                    // Placeholder mejorado con negrita
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content('Vas a registrar una llamada realizada a este LEADS. Debes indicar cuando es la proxima agenda del mismo.'),
                
                    Toggle::make('actualizar_agenda')
                        ->label('Nuevo seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)                    

                                        // --- HINT CON FORMATO HUMANO + FECHA ---
                        ->hint(function (?Lead $record): ?string {
                            if ($record?->agenda) {
                              // Calcula la diferencia legible para humanos
                                $humanDiff = $record->agenda->diffForHumans(); // Ej: "en 2 días", "hace 1 hora"

                                // Formatea la fecha/hora absoluta (usa isoFormat para nombres de mes/día en español)
                                $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm'); // Ej: "sábado 19 de abril, 10:30"
                                // Alternativa más simple si no necesitas nombres: $record->agenda->format('d/m/Y H:i')
                                // Combina ambos en el texto del hint
                                return "Actualmente agendado para llamar {$humanDiff} (el {$formattedDate})";
                            }
                            return 'No hay seguimiento agendado.'; // Texto si no hay fecha
                        })
                        ->hintIcon('heroicon-m-information-circle'), // Opcional: icono para el hint
                        // --- FIN AÑADIDO ---
                    
                    DateTimePicker::make('agenda_nueva')
                        // ... (como estaba, con visible(), required(), after(), etc.) ...
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Llamada a " . ($record?->nombre ?? 'este lead')) // <-- Añadir esta versión dinámica
                ->modalSubmitActionLabel('Registrar llamada')
                ->modalWidth('xl') // Prueba con 'large' o 'xl' si prefieres más ancho
             
                 // La lógica de la acción al pulsar "Registrar" sigue siendo la misma
                 ->action(function (array $data, Lead $record) {
                    $currentUser = Auth::user(); // Obtenemos el usuario actual una vez
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name' o 'full_name'
                
                    // 1. Incrementar contador
                    $record->increment('llamadas');
                
                    // 2. Determinar y ACTUALIZAR la agenda ANTES de crear el comentario
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda; // Empezamos con la fecha que ya tenía el lead
                
                    // Comprobamos si el usuario marcó actualizar y si hay una nueva fecha válida
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try {
                            // Intentamos convertir la fecha del formulario a objeto Carbon
                            $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']);
                
                            // Actualizamos el campo agenda en el objeto $record
                            $record->agenda = $nuevaFechaAgenda;
                
                            // Guardamos el cambio en la BD AHORA MISMO
                            $record->save();
                
                            // Actualizamos la variable que usaremos para el comentario
                            $fechaAgendaFinal = $nuevaFechaAgenda;
                            $agendaActualizada = true; // Marcamos que sí se actualizó
                
                        } catch (\Exception $e) {
                            // Si la fecha del formulario no es válida, notificamos y salimos
                            Notification::make()->title('Error al procesar fecha')->body('La fecha de agenda proporcionada no es válida.')->danger()->send();
                            return; // Detenemos la acción aquí
                        }
                    }
                
                    // 3. Construir el texto del comentario
                    $textoComentario = "Llamada registrada por {$userName}.";
                
                    // Añadimos información de la agenda si existe una fecha final
                    if ($fechaAgendaFinal instanceof Carbon) { // Comprobamos que sea un objeto Carbon válido
                        // Asegúrate que Carbon/Laravel tiene el locale 'es' configurado para diffForHumans
                        $textoRelativo = $fechaAgendaFinal->diffForHumans(); // Ej: "en 2 días", "hace 1 hora"
                        $textoComentario .= " Próximo seguimiento: {$textoRelativo}.";
                    } else {
                        $textoComentario .= " No hay próximo seguimiento agendado.";
                    }
                
                    // 4. Crear el comentario polimórfico
                    $record->comentarios()->create([
                        'user_id' => $currentUser->id,
                        'contenido' => $textoComentario // Usamos el texto construido
                    ]);
                
                    // 5. Enviar Notificación final
                    if ($agendaActualizada) {
                        Notification::make()->title('Llamada registrada y agenda actualizada')->success()->send();
                    } else {
                        Notification::make()->title('Llamada registrada')->success()->send();
                    }
                
                    // Ya no hace falta $record->save() aquí si no se modificó la agenda,
                    // porque el increment() guarda directo y la agenda se guardó antes si cambió.
                }),
                
        
               // --- Acción Enviar Email ---
               Tables\Actions\Action::make('enviarEmail')
                ->icon('heroicon-o-envelope') // Icono cambiado
                ->label('')
                ->tooltip('Registrar Email Enviado y Opcionalmente Reagendar') // Texto cambiado
                ->color('warning') // Color cambiado (ejemplo)
                ->form([
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content(new HtmlString('<strong>Registrar Email:</strong> Confirma la acción y, si lo necesitas, indica la nueva fecha para el próximo seguimiento.')), // Texto cambiado

                    Toggle::make('actualizar_agenda')
                        ->label('Reagendar Próximo Seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)
                        ->hint(function (?Lead $record): ?string { // Lógica del Hint idéntica
                            if ($record?->agenda) {
                                $humanDiff = $record->agenda->diffForHumans();
                                $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm');
                                return "Actualmente agendado {$humanDiff} (el {$formattedDate})"; // Texto ligeramente adaptado
                            }
                            return 'No hay seguimiento agendado.';
                        })
                        ->hintIcon('heroicon-m-information-circle'),

                    DateTimePicker::make('agenda_nueva')
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Email a " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
                ->modalSubmitActionLabel('Registrar email') // Botón cambiado
                ->modalWidth('xl')
                ->action(function (array $data, Lead $record) { // Lógica de acción adaptada
                    $currentUser = Auth::user();
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name'

                    // 1. Incrementar contador específico
                    $record->increment('emails'); // <-- Cambiado a 'emails'

                    // 2. Determinar y actualizar agenda (lógica idéntica)
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda;
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try {
                            $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']);
                            $record->agenda = $nuevaFechaAgenda;
                            $record->save();
                            $fechaAgendaFinal = $nuevaFechaAgenda;
                            $agendaActualizada = true;
                        } catch (\Exception $e) {
                            Notification::make()->title('Error al procesar fecha')->danger()->send();
                            return;
                        }
                    }

                    // 3. Construir y crear el comentario (texto adaptado)
                    $textoComentario = "Email enviado por {$userName}."; // <-- Texto cambiado
                    if ($fechaAgendaFinal instanceof Carbon) {
                        $textoRelativo = $fechaAgendaFinal->diffForHumans();
                        $textoComentario .= " Próximo seguimiento: {$textoRelativo}.";
                    } else {
                        $textoComentario .= " No hay próximo seguimiento agendado.";
                    }
                    $record->comentarios()->create([
                        'user_id' => $currentUser->id,
                        'contenido' => $textoComentario
                    ]);

                    // 4. Enviar Notificación (texto adaptado)
                    if ($agendaActualizada) {
                        Notification::make()->title('Email registrado y agenda actualizada')->success()->send(); // <-- Texto cambiado
                    } else {
                        Notification::make()->title('Email registrado')->success()->send(); // <-- Texto cambiado
                    }
                }),


                // --- Acción Chat ---
                Tables\Actions\Action::make('chat')
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->label('')
                ->tooltip('Registrar Chat y Opcionalmente Reagendar') // Texto cambiado
                ->color('success') // Color cambiado (ejemplo)
                ->form([ // Lógica del formulario idéntica a 'llamar', solo cambia el texto del placeholder
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content(new HtmlString('<strong>Registrar Chat:</strong> Confirma la acción y, si lo necesitas, indica la nueva fecha para el próximo seguimiento.')), // Texto cambiado
                    Toggle::make('actualizar_agenda') // Resto del form idéntico...
                        ->label('Reagendar Próximo Seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)
                        ->hint(function (?Lead $record): ?string { /* ... misma lógica hint ... */ if ($record?->agenda) { $humanDiff = $record->agenda->diffForHumans(); $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm'); return "Actualmente agendado {$humanDiff} (el {$formattedDate})"; } return 'No hay seguimiento agendado.'; })
                        ->hintIcon('heroicon-m-information-circle'),
                    DateTimePicker::make('agenda_nueva') // Resto del form idéntico...
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Chat con " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
                ->modalSubmitActionLabel('Registrar chat') // Botón cambiado
                ->modalWidth('xl')
                ->action(function (array $data, Lead $record) { // Lógica de acción adaptada
                    $currentUser = Auth::user();
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name'

                    // 1. Incrementar contador específico
                    $record->increment('chats'); // <-- Cambiado a 'chats'

                    // 2. Determinar y actualizar agenda (lógica idéntica)
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda;
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try { $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']); $record->agenda = $nuevaFechaAgenda; $record->save(); $fechaAgendaFinal = $nuevaFechaAgenda; $agendaActualizada = true; } catch (\Exception $e) { Notification::make()->title('Error al procesar fecha')->danger()->send(); return; }
                    }

                    // 3. Construir y crear el comentario (texto adaptado)
                    $textoComentario = "Chat registrado por {$userName}."; // <-- Texto cambiado
                    if ($fechaAgendaFinal instanceof Carbon) { $textoRelativo = $fechaAgendaFinal->diffForHumans(); $textoComentario .= " Próximo seguimiento: {$textoRelativo}."; } else { $textoComentario .= " No hay próximo seguimiento agendado."; }
                    $record->comentarios()->create([ 'user_id' => $currentUser->id, 'contenido' => $textoComentario ]);

                    // 4. Enviar Notificación (texto adaptado)
                    if ($agendaActualizada) { Notification::make()->title('Chat registrado y agenda actualizada')->success()->send(); } else { Notification::make()->title('Chat registrado')->success()->send(); } // <-- Texto cambiado
                }),


                // --- Acción Otros ---
                Tables\Actions\Action::make('otros')
                ->icon('heroicon-o-paper-airplane')
                ->label('')
                ->tooltip('Registrar Otra Acción y Opcionalmente Reagendar') // Texto cambiado
                ->color('gray') // Color cambiado (ejemplo)
                ->form([ // Lógica del formulario idéntica a 'llamar', solo cambia el texto del placeholder
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content(new HtmlString('<strong>Registrar Otra Acción:</strong> Confirma la acción y, si lo necesitas, indica la nueva fecha para el próximo seguimiento.')), // Texto cambiado
                    Toggle::make('actualizar_agenda') // Resto del form idéntico...
                        ->label('Reagendar Próximo Seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)
                        ->hint(function (?Lead $record): ?string { /* ... misma lógica hint ... */ if ($record?->agenda) { $humanDiff = $record->agenda->diffForHumans(); $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm'); return "Actualmente agendado {$humanDiff} (el {$formattedDate})"; } return 'No hay seguimiento agendado.'; })
                        ->hintIcon('heroicon-m-information-circle'),
                    DateTimePicker::make('agenda_nueva') // Resto del form idéntico...
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Otra Acción para " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
                ->modalSubmitActionLabel('Registrar acción') // Botón cambiado
                ->modalWidth('xl')
                ->action(function (array $data, Lead $record) { // Lógica de acción adaptada
                    $currentUser = Auth::user();
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name'

                    // 1. Incrementar contador específico
                    $record->increment('otros_acciones'); // <-- Cambiado a 'otros_acciones'

                    // 2. Determinar y actualizar agenda (lógica idéntica)
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda;
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try { $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']); $record->agenda = $nuevaFechaAgenda; $record->save(); $fechaAgendaFinal = $nuevaFechaAgenda; $agendaActualizada = true; } catch (\Exception $e) { Notification::make()->title('Error al procesar fecha')->danger()->send(); return; }
                    }

                    // 3. Construir y crear el comentario (texto adaptado)
                    $textoComentario = "Otra acción registrada por {$userName}."; // <-- Texto cambiado
                    if ($fechaAgendaFinal instanceof Carbon) { $textoRelativo = $fechaAgendaFinal->diffForHumans(); $textoComentario .= " Próximo seguimiento: {$textoRelativo}."; } else { $textoComentario .= " No hay próximo seguimiento agendado."; }
                    $record->comentarios()->create([ 'user_id' => $currentUser->id, 'contenido' => $textoComentario ]);

                    // 4. Enviar Notificación (texto adaptado)
                    if ($agendaActualizada) { Notification::make()->title('Otra acción registrada y agenda actualizada')->success()->send(); } else { Notification::make()->title('Otra acción registrada')->success()->send(); } // <-- Texto cambiado
                }),

        ])
        ->bulkActions([ // Acciones Masivas
            Tables\Actions\BulkActionGroup::make([
                ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('leads')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                    \pxlrbt\FilamentExcel\Columns\Column::make('id'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('nombre')
                       ->heading('Nombre'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('email')
                        ->heading('Email'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('tfn')
                        ->heading('Teléfono'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('procedencia.procedencia')
                        ->heading('Procedencia'),                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('creador.name')
                        ->heading('Creador'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('asignado.name')
                        ->heading('Asignado'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('estado')
                        ->heading('Estado'),    
                    \pxlrbt\FilamentExcel\Columns\Column::make('demandado')
                        ->heading('Demandado'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_gestion')
                        ->heading('Fecha de gestión')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('agenda')
                        ->heading('Agendado')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_cierre')
                        ->heading('Fecha de cierre')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('observacion_cierre')
                        ->heading('Observaciones cierre'),
                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('motivoDescarte.motivo')
                        ->heading('Motivo de descarte'),  
                    \pxlrbt\FilamentExcel\Columns\Column::make('cliente.nombre')
                        ->heading('Cliente'),                     
                    \pxlrbt\FilamentExcel\Columns\Column::make('llamadas')
                        ->heading('Llamadas'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('emails')
                        ->heading('Emails'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('chats')
                        ->heading('Chats'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('otros_acciones')
                        ->heading('Otras acciones'),                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('observaciones')
                        ->heading('Observaciones'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('created_at')
                        ->heading('Creado en App')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('updated_at')
                        ->heading('Actualizado en App')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),

                        
                ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar Leads Seleccionados')
        ->modalDescription('Exportarás todos los datos de los Leads seleccionados.'),
                Tables\Actions\DeleteBulkAction::make(),
                // ExportBulkAction::make(), // Si usas exportación

                // Acción Masiva: Asignar (Movida aquí y adaptada)
                BulkAction::make('asignarComercial')
                    ->label('Asignar Comercial')
                    ->icon('heroicon-o-users')
                    ->form([
                        Select::make('asignado_id_masivo') // Nombre diferente para evitar conflictos
                            ->label('Asignar a')
                            ->options(
                                // Obtener solo usuarios con rol 'comercial'
                                User::whereHas('roles', fn (Builder $q) => $q->where('name', 'comercial'))
                                    ->pluck('name', 'id') // Ajusta 'name' si es 'full_name'
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (array $data, EloquentCollection $records){
                        $userID = $data['asignado_id_masivo'];
                        $records->each->update(['asignado_id' => $userID]); // Actualiza cada registro

                        // Notificar al usuario asignado (opcional, puede ser pesado si son muchos leads)
                        $assignedUser = User::find($userID);
                        if($assignedUser) {
                             Notification::make()
                                ->title('Nuevos Leads Asignados')
                                ->icon('heroicon-o-user-group')
                                ->info()
                                ->body("Te han asignado {$records->count()} lead(s).")
                                ->sendToDatabase($assignedUser); // Enviar al usuario objeto
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Asignar Leads Seleccionados')
                    ->modalDescription('Selecciona el comercial al que quieres asignar estos leads.')
                    ->modalSubmitActionLabel('Asignar')
                    ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
