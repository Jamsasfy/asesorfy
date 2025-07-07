<?php

namespace App\Filament\Resources;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Filament\Resources\VentaResource\Pages;
use App\Filament\Resources\VentaResource\RelationManagers;
use App\Models\Servicio;
use App\Models\Venta;
use App\Models\VentaItem;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Services\ConfiguracionService; // ¡Añade esta línea!
use App\Enums\ServicioTipoEnum; // <-- Esta es la línea que debe estar aquí
use App\Models\ClienteSuscripcion;
use Filament\Tables\Enums\FiltersLayout;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Carbon\Carbon;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\HtmlString;
use App\Models\Proyecto;
use App\Enums\ProyectoEstadoEnum;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;


class VentaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Venta::class;


    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar'; // O un icono de venta
    protected static ?string $navigationGroup = 'Gestión VENTAS'; // O un grupo propio de Ventas
   // protected static ?string $navigationLabel = 'Admin Ventas';
    protected static ?string $modelLabel = 'Venta';
    protected static ?string $pluralModelLabel = 'Admin Ventas';

      public static function getNavigationLabel(): string
    {
        if (auth()->check() && auth()->user()->hasRole('comercial')) {
            return 'Mis Ventas';
        }

        return 'Admin Ventas';
    }
    public static function getEloquentQuery(): Builder
{
    /** @var \App\Models\User|null $user */
    $user = Auth::user();
    // Empezamos con la consulta base y las precargas que ya tenías
    $query = parent::getEloquentQuery()->with(['items.servicio', 'cliente', 'comercial']); // Añadí cliente y comercial a with para eficiencia

    if (!$user) {
        return $query->whereRaw('1 = 0'); // No hay usuario, no mostrar nada
    }

    // Para depurar (descomenta si es necesario):
    // \Illuminate\Support\Facades\Log::info('User in VentaResource::getEloquentQuery():', ['email' => $user->email, 'roles' => $user->getRoleNames()->toArray()]);

    if ($user->hasRole('super_admin')) {
        // El super_admin ve todas las ventas
        return $query;
    }

    if ($user->hasRole('comercial')) {
        // El comercial solo ve sus ventas.
        // Asumiendo que el campo en la tabla 'ventas' es 'user_id' para el comercial.
        // Si tu campo se llama 'comercial_id', cámbialo aquí.
        return $query->where('user_id', $user->id);
    }
    
    // Lógica para otros roles (ej. un jefe de ventas podría ver las ventas de su equipo)
    // if ($user->hasRole('jefe_ventas')) {
    //     $ids_comerciales_equipo = User::where('jefe_id_en_user_table', $user->id)->pluck('id')->toArray();
    //     return $query->whereIn('user_id', $ids_comerciales_equipo); // Asume que 'user_id' es el comercial en Venta
    // }

    // Por defecto, si el rol no está contemplado arriba y no es admin, no muestra ventas.
    return $query->whereRaw('1 = 0');
}

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'boton_crear_venta',
        ];
    }

    
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Datos de la Venta')
                ->columns(3)
                ->schema([
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->relationship('cliente', 'razon_social')
                        ->required()
                        ->default(fn () => request()->query('cliente_id'))
                        ->searchable()
                        ->preload()
                        ->columnSpan(1)
                        ->reactive()
                        ->suffixIcon('heroicon-m-user'),

                    Select::make('lead_id')
                        ->label('Lead de Origen')
                        ->relationship('lead', 'nombre')
                        ->nullable(false)
                        ->required()
                        ->searchable()
                        ->default(fn () => request()->query('lead_id'))
                        ->preload()
                        ->columnSpan(1)
                        ->suffixIcon('heroicon-m-identification'),

                    Select::make('user_id')
                        ->label('Comercial')
                        ->relationship('comercial', 'name')
                        ->default(Auth::id())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(1)
                        ->suffixIcon('heroicon-m-briefcase'),

                    DateTimePicker::make('fecha_venta')
                        ->label('Fecha de Venta')
                        ->native(false)
                        ->required()
                        ->default(now())
                        ->columnSpan(1),

                    TextInput::make('importe_total')
                        ->label('Importe Total de la Venta')
                        ->suffix('€')
                        ->readOnly()
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(1),

                    Textarea::make('observaciones')
                        ->label('Observaciones de la Venta')
                        ->nullable()
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Items de la Venta')
                ->description('Añade los servicios incluidos en esta venta.')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->schema([
                            Select::make('servicio_id')
                                ->label('Servicio')
                                ->relationship(
                                    'servicio',
                                    'nombre',
                                    function (Builder $query, Get $get) {
                                        $clienteId = $get('../../cliente_id');
                                        if (!$clienteId) {
                                            return;
                                        }

                                        $tienePrincipal = ClienteSuscripcion::query()
                                            ->where('cliente_id', $clienteId)
                                            ->where('es_tarifa_principal', true)
                                            ->whereIn('estado', [
                                                ClienteSuscripcionEstadoEnum::ACTIVA->value,
                                                ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION->value,
                                            ])
                                            ->exists();

                                        if ($tienePrincipal) {
                                            $query->where('es_tarifa_principal', false);
                                        }
                                    }
                                )
                                ->required()
                                ->searchable()
                                ->preload()
                                ->distinct()
                                ->live()
                                ->columnSpan(2)
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                    if ($state && $servicio = Servicio::find($state)) {
                                        $set('precio_unitario', $servicio->precio_base);
                                    } else {
                                        $set('precio_unitario', 0);
                                    }
                                    self::updateTotals($get, $set);
                                }),

                            TextInput::make('nombre_personalizado')
                                ->label('Nombre del Servicio (editable)')
                                ->placeholder('Ej. Servicio jurídico especial...')
                                ->helperText('Solo si el servicio permite nombre personalizado.')
                                ->columnSpan(2)
                                ->required()
                                ->visible(fn (Get $get) => optional(Servicio::find($get('servicio_id')))->es_editable),

                            Toggle::make('requiere_proyecto')
                                ->label('¿Este servicio requiere proyecto?')
                                ->helperText('Se generará un proyecto si está activo.')
                                ->columnSpan(2)
                                ->default(false)
                                ->visible(fn (Get $get) => optional(Servicio::find($get('servicio_id')))->es_editable),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()->type('text')->inputMode('numeric')
                                ->required()
                                ->default(1)
                                ->minValue(1)
                                ->live()
                                ->columnSpan(1)
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                            TextInput::make('precio_unitario')
                                ->label('Precio Base (€)')
                                ->helperText('Precio unitario original del servicio, sin IVA ni descuentos.')
                                ->numeric()->type('text')->inputMode('decimal')
                                ->required()
                                ->suffix('€')
                                ->columnSpan(1)
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                            TextInput::make('subtotal')
                                ->label('Subtotal Base (€)')
                                ->helperText('Subtotal de la línea, sin descuentos ni IVA.')
                                ->numeric()->type('text')
                                ->readOnly()
                                ->suffix('€')
                                ->columnSpan(1),

                            Forms\Components\Hidden::make('precio_unitario_aplicado')->dehydrated(true),
                            Forms\Components\Hidden::make('subtotal_aplicado')->dehydrated(true),
                            Forms\Components\Hidden::make('subtotal_aplicado_con_iva')->dehydrated(true),

                            TextInput::make('subtotal_con_iva')
                                ->label('Subtotal con IVA (Base)')
                                ->numeric()->type('text')
                                ->readOnly()->suffix('€')
                                ->columnSpan(1)
                                ->dehydrated(true),

                           DatePicker::make('fecha_inicio_servicio')
                        ->label('Inicio Servicio')
                        ->native(false)
                        ->nullable()
                        ->required(function (Get $get): bool {
                            // --- LÓGICA DE VISIBILIDAD MOVIDA A 'required' ---
                            // 1. Si el servicio actual no es recurrente, el campo no es obligatorio.
                            $servicioId = $get('servicio_id');
                            if (!$servicioId) return false;
                            
                            $servicio = Servicio::find($servicioId);
                            if (!$servicio || $servicio->tipo->value !== 'recurrente') {
                                return false;
                            }

                            // 2. Comprobamos si CUALQUIER item en la venta requiere un proyecto.
                            $todosLosItems = $get('../../items') ?? [];
                            foreach ($todosLosItems as $itemState) {
                                $itemServicioId = $itemState['servicio_id'] ?? null;
                                if (!$itemServicioId) continue;
                                
                                $itemServicio = Servicio::find($itemServicioId);
                                if (!$itemServicio) continue;
                                
                                // La nueva condición que comprueba ambos casos
                                $esteItemRequiereProyecto = $itemServicio->es_editable
                                    ? ($itemState['requiere_proyecto'] ?? false)
                                    : $itemServicio->requiere_proyecto_activacion;

                                if ($esteItemRequiereProyecto) {
                                    // Si encontramos un proyecto, el campo NO es obligatorio.
                                    return false; 
                                }
                            }

                            // 3. Si no se encontró ningún proyecto en toda la venta, el campo SÍ es obligatorio.
                            return true;
                        })
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            $duracion = (int) $get('descuento_duracion_meses');

                            if ($state && $duracion > 0) {
                                $fechaFin = Carbon::parse($state)
                                    ->addMonths($duracion - 1)
                                    ->endOfMonth()
                                    ->format('Y-m-d');

                                $set('descuento_valido_hasta', $fechaFin);
                            }
                        })
                        ->columnSpan(2),

                            Textarea::make('observaciones_item')
                                ->label('Notas del servicio')
                                ->nullable()
                                ->rows(1)
                                ->columnSpan(4),

                           Section::make('Aplicar Descuento')
    ->collapsible()
    ->collapsed()
    ->schema([
        Select::make('descuento_tipo')
            ->label('Tipo de Descuento')
            ->placeholder('Sin descuento')
            ->options([
                'porcentaje' => 'Porcentaje (%)',
                'fijo'       => 'Cantidad Fija (€)',
                'precio_final' => 'Precio Final (€)',
            ])
            ->nullable()
            ->live()
            ->columnSpan(2)
            ->dehydrated(true)
            ->afterStateUpdated(function(Get $get, Set $set) {
                $set('descuento_valor', null);
                $set('descuento_duracion_meses', null);
                $set('descuento_valido_hasta', null);
                $set('observaciones_descuento', null);
                VentaResource::updateTotals($get, $set);
            }),

        TextInput::make('descuento_valor')
            ->label('Valor del Descuento')
            ->numeric()->type('text')->inputMode('decimal')
            ->nullable()
            ->live()
            ->columnSpan(2)
            ->visible(fn (Get $get) => !empty($get('descuento_tipo')))
            ->suffix(fn(Get $get):?string => match($get('descuento_tipo')) {
                'porcentaje' => '%',
                'fijo', 'precio_final' => '€',
                default => null
            })
            ->helperText(fn(Get $get):?string => match($get('descuento_tipo')) {
                'porcentaje' => 'Introduce solo el número del porcentaje (ej: 50).',
                'fijo'       => 'Introduce la cantidad fija que se descontará.',
                'precio_final' => 'Introduce el precio final que tendrá esta línea.',
                default      => null
            })
            ->dehydrated(true)
            ->afterStateUpdated(fn (Get $get, Set $set) => VentaResource::updateTotals($get, $set)),

        TextInput::make('descuento_duracion_meses')
            ->label('Duración (meses)')
            ->numeric()
            ->type('text')
            ->inputMode('numeric')
            ->nullable()
            ->columnSpan(1)
            ->live()
            ->dehydrated(true)
            ->visible(function (Get $get): bool {
                if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                    return false;
                }
                return \App\Models\Servicio::find($servicioId)?->tipo?->value === 'recurrente';
            })
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                $fechaInicio = $get('fecha_inicio_servicio');
                $duracion = (int) $state;

                if ($fechaInicio && $duracion > 0) {
                    $fechaFin = \Carbon\Carbon::parse($fechaInicio)
                        ->addMonths($duracion - 1)
                        ->endOfMonth()
                        ->format('Y-m-d');

                    $set('descuento_valido_hasta', $fechaFin);
                } else {
                    $set('descuento_valido_hasta', null);
                }

                VentaResource::updateTotals($get, $set);
            }),

        DatePicker::make('descuento_valido_hasta')
            ->label('Dto Válido Hasta')
            ->native(false)
            ->nullable()
            ->readOnly()
            ->columnSpan(2)
            ->placeholder('Se calcula automáticamente')
            ->visible(function (Get $get): bool {
                if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                    return false;
                }
                return \App\Models\Servicio::find($servicioId)?->tipo?->value === 'recurrente';
            })
            ->dehydrated(true),

        Textarea::make('observaciones_descuento') 
            ->label('Descripción del Descuento')
            ->nullable()
            ->columnSpan(3)
            ->visible(fn (Get $get) => !empty($get('descuento_tipo')))
            ->dehydrated(true),

        TextInput::make('subtotal_aplicado') 
            ->label('Final (sin IVA)')
            ->numeric()->type('text')
            ->readOnly()
            ->columnSpan(1)
            ->suffix('€')
            ->visible(fn (Get $get) => !empty($get('descuento_tipo'))),

        TextInput::make('subtotal_aplicado_con_iva') 
            ->label('Final (con IVA)')
            ->numeric()->type('text')
            ->readOnly()
            ->columnSpan(1)
            ->suffix('€')
            ->visible(fn (Get $get) => !empty($get('descuento_tipo'))),
    ])
    ->columns(12)
    ->columnSpanFull(),


                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->reorderable(true)
                        ->collapsible()
                        ->cloneable()
                        ->minItems(1)
                        ->addActionLabel('Añadir Servicio')
                        ->live(),

                        
                ])
                ->columnSpanFull(),
        ]);
}


    private static function updateTotals(Get $get, Set $set): void
    {
        $cantidad = (float)($get('cantidad') ?? 1);
        $precioUnitario = (float)($get('precio_unitario') ?? 0);
        $subtotal = round($cantidad * $precioUnitario, 2);
        $set('subtotal', $subtotal);

        $ivaPorcentaje = ConfiguracionService::get('IVA_general', 0.21);
        $subtotalConIva = round($subtotal * (1 + $ivaPorcentaje), 2);
        $set('subtotal_con_iva', $subtotalConIva);

        $descuentoTipo = $get('descuento_tipo');
        $descuentoValor = (float)($get('descuento_valor') ?? 0);
        $precioFinalConDto = $subtotal;

        if (!empty($descuentoTipo) && is_numeric($descuentoValor) && $descuentoValor > 0) {
            switch ($descuentoTipo) {
                case 'porcentaje':
                    $precioFinalConDto = round($subtotal - ($subtotal * ($descuentoValor / 100)), 2);
                    break;
                case 'fijo':
                    $precioFinalConDto = round($subtotal - $descuentoValor, 2);
                    break;
                case 'precio_final':
                    $precioFinalConDto = round($descuentoValor, 2);
                    break;
            }
        }
        $precioFinalConDto = max(0, $precioFinalConDto);

        $set('subtotal_aplicado', $precioFinalConDto); 
        $set('subtotal_aplicado_con_iva', round($precioFinalConDto * (1 + $ivaPorcentaje), 2));
    }


    public static function table(Table $table): Table
    {
        return $table
        ->paginated([25, 50, 100, 'all']) // Ajusta opciones si quieres
        ->striped()
        ->recordUrl(null)    // Esto quita la navegación al hacer clic en la fila
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
            ->columns([
                Tables\Columns\TextColumn::make('cliente.razon_social')
                    ->label('Cliente')
                    ->url(fn (Venta $record): ?string => 
                    $record->cliente_id
                        ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                        : null
                    )
                    // Color amarillo (warning) si es enlace, gris si no
                    ->color(fn (Venta $record): ?string =>
                        $record->cliente_id
                            ? 'warning'
                            : null
                    )
                    ->searchable()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('lead_id')
                    ->label('Lead Asociado')
                    ->formatStateUsing(fn ($state, $record) => "Ver lead #{$record->lead_id}")
                    ->badge()
                    ->color('warning')
                    ->url(fn ($record) => LeadResource::getUrl('view', [
                        'record' => $record->lead_id,
                    ]))
                    ->openUrlInNewTab()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comercial.full_name')
                ->label('Vendido por')
                   ->badge()
                   ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_venta')
                    ->dateTime('d/m/y - H:m')
                    ->sortable(),
                
            TextColumn::make('importe_recurrente')
           ->label('Recurrente')
           ->getStateUsing(function (Venta $record): string {
               $totalRec = VentaItem::query()
                   ->where('venta_id', $record->id)
                   ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'recurrente'))
                   ->sum('subtotal');

               return number_format($totalRec, 2, ',', '.') . ' €';
           })
           ->sortable(false),

       TextColumn::make('importe_unico')
           ->label('Único')
           ->getStateUsing(function (Venta $record): string {
               $totalUnico = VentaItem::query()
                   ->where('venta_id', $record->id)
                   ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'unico'))
                   ->sum('subtotal');

               return number_format($totalUnico, 2, ',', '.') . ' €';
           })
           ->sortable(false),

    

     // COLUMNA: Descuento Mensual Recurrente
            TextColumn::make('descuento_mensual_recurrente_total')
                ->label('Dto. Mensual Rec.')
                ->badge()
                ->formatStateUsing(function ($state, Venta $record): string {
                    if ((float)$state > 0) {
                        $duracionTexto = '';
                        $descuentoEnMesesDetectado = false;
                        foreach ($record->items as $item) {
                            $item->loadMissing('servicio');
                            if ($item->servicio && $item->servicio->tipo->value === 'recurrente' && !empty($item->descuento_duracion_meses) && (float)$item->descuento_valor > 0) {
                                $duracionTexto = " - {$item->descuento_duracion_meses} meses";
                                $descuentoEnMesesDetectado = true;
                                break;
                            }
                        }
                        return '-' . number_format($state, 2, ',', '.') . ' €/mes' . ($descuentoEnMesesDetectado ? $duracionTexto : '');
                    }
                    return 'Sin Dto.'; // <<< CAMBIO AQUI: Texto para cuando no hay descuento
                })
               ->color(function ($state): string {
                    // <<< CAMBIO AQUI: Color condicional
                    return ((float)$state > 0) ? 'danger' : 'info'; // 'danger' para rojo, 'gray' para azul más oscuro o gris
                })
                ->sortable(false)
                ->toggleable(isToggledHiddenByDefault: false),

            // COLUMNA: Descuento Único Total
            TextColumn::make('descuento_unico_total') // Usa el accesor
                ->label('Dto. Único') // Etiqueta clara
                ->badge()
                // Formato que muestra solo el monto
                ->formatStateUsing(function ($state): string {
                    if ((float)$state > 0) {
                        return '-' . number_format($state, 2, ',', '.') . ' €'; // Muestra el monto total
                    }
                    return 'Sin Dto.';
                })
                // Color del badge
                ->color(function ($state): string {
                    return ((float)$state > 0) ? 'danger' : 'info';
                })
                // <<< ELIMINADO: Tooltip
                ->sortable(false)
                ->toggleable(isToggledHiddenByDefault: false),


                Tables\Columns\TextColumn::make('importe_total')
                    ->label('Importe Total')
                    ->color('success')
                   ->size('lg')
                    ->icon('heroicon-o-currency-euro')
                    ->iconPosition('after')
                    ->iconColor('warning')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                ->label('Venta creada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                ->label('Venta actualizada')
                ->dateTime('d/m/y - H:m')
                    ->sortable(),
                   
            ])
            ->filters([
                 Tables\Filters\SelectFilter::make('cliente_id')
                    ->relationship('cliente', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Cliente'),

              Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('comercial', 'name', fn (Builder $query) => 
                        // Filtra para mostrar usuarios con el rol 'comercial' O 'super_admin'
                        $query->whereHas('roles', fn (Builder $query) => 
                            $query->where('name', 'comercial')
                                  ->orWhere('name', 'super_admin') // <<< CAMBIO AQUI: Añadir super_admin
                        )
                    )
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Comercial'),

                // Filtro por Tipo de Servicio (Único/Recurrente)
                Tables\Filters\SelectFilter::make('tipo_servicio')
                    ->options([
                        'unico'      => 'Servicio Único',    // Usa la cadena literal 'unico'
                        'recurrente' => 'Servicio Recurrente', // Usa la cadena literal 'recurrente'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Este filtro necesita un join con venta_items y servicios
                        if (isset($data['value']) && filled($data['value'])) {
                            $query->whereHas('items', function (Builder $query) use ($data) {
                                $query->whereHas('servicio', function (Builder $query) use ($data) {
                                    $query->where('tipo', $data['value']);
                                });
                            });
                        }
                        return $query;
                    })
                    ->label('Filtrar por Tipo de Servicio'),

                // Filtro por Rango de Fechas de Venta
                DateRangeFilter::make('fecha_venta')
                    
                   
                    ->label('Filtrar por Fecha de Venta'),

                // Filtro por si tiene Descuento (cualquier tipo)
                Tables\Filters\Filter::make('con_descuento')
                    ->query(function (Builder $query): Builder {
                        // Filtra ventas que tengan al menos un item con descuento
                        return $query->whereHas('items', function (Builder $query) {
                            $query->where(function (Builder $query) {
                                // Donde descuento_tipo NO es nulo Y descuento_valor es mayor que 0
                                $query->whereNotNull('descuento_tipo')
                                      ->where('descuento_valor', '>', 0);
                            });
                        });
                    })
                    ->toggle() // Se activa/desactiva con un switch
                    ->label('Mostrar con Descuento'),
            ],layout: FiltersLayout::AboveContent)
                ->filtersFormColumns(7)
            ->actions([
                // 1. Añadimos la acción para VER los detalles (el ojo)
                    Tables\Actions\ViewAction::make()
                        ->label('') // Sin texto, solo el icono
                        ->tooltip('Ver Venta'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                     ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('ventas')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                   // Columnas ya existentes
                                \pxlrbt\FilamentExcel\Columns\Column::make('id')
                                    ->heading('ID Venta'), // Etiqueta más clara
                                \pxlrbt\FilamentExcel\Columns\Column::make('cliente.razon_social')
                                    ->heading('Cliente'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('lead.id') // Usar lead.nombre para el nombre del Lead
                                    ->heading('Lead Asociado')
                                    ->formatStateUsing(fn ($state, $record) => $record->lead ? $record->lead->nombre : ''), // Asegura que solo muestre el nombre si existe
                                \pxlrbt\FilamentExcel\Columns\Column::make('comercial.full_name')
                                    ->heading('Vendido por'),
                                
                                \pxlrbt\FilamentExcel\Columns\Column::make('fecha_venta')
                                    ->heading('Fecha de venta')
                                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y H:i')), // Formato para Excel
                                
                             // IMPORTE RECURRENTE (USANDO LA LÓGICA DE LA COLUMNA DE LA TABLA)
                                    \pxlrbt\FilamentExcel\Columns\Column::make('importe_recurrente')
                                        ->heading('Importe Recurrente')
                                        // <<< CAMBIO AQUI: Usar la cadena literal 'recurrente'
                                        ->getStateUsing(function (Venta $record): float {
                                            $totalRec = VentaItem::query()
                                                ->where('venta_id', $record->id)
                                                ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'recurrente'))
                                                ->sum('subtotal_aplicado'); // Suma subtotal_aplicado para el valor con descuento
                                            return (float) $totalRec;
                                        })
                                        ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                                    
                                    // IMPORTE ÚNICO (USANDO LA LÓGICA DE LA COLUMNA DE LA TABLA)
                                    \pxlrbt\FilamentExcel\Columns\Column::make('importe_unico')
                                        ->heading('Importe Único')
                                        // <<< CAMBIO AQUI: Usar la cadena literal 'unico'
                                        ->getStateUsing(function (Venta $record): float {
                                            $totalUnico = VentaItem::query()
                                                ->where('venta_id', $record->id)
                                                ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'unico'))
                                                ->sum('subtotal_aplicado'); // Suma subtotal_aplicado para el valor con descuento
                                            return (float) $totalUnico;
                                        })
                                        ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                                    
                                    
                                
                                // DESCUENTO MENSUAL RECURRENTE (CORRECCIÓN EN formatStateUsing)
                                \pxlrbt\FilamentExcel\Columns\Column::make('descuento_mensual_recurrente_total')
                                    ->heading('Descuento Mensual Rec.')
                                    ->formatStateUsing(function ($state, $record) {
                                        if ((float)$state > 0) {
                                            $duracionTexto = '';
                                            // <<< CORRECCIÓN AQUI: Acceso al valor del Enum directamente como cadena
                                            $recurrente_value = 'recurrente'; // Definir la cadena literal aquí
                                            foreach ($record->items as $item) {
                                                $item->loadMissing('servicio');
                                                if ($item->servicio && $item->servicio->tipo->value === $recurrente_value && !empty($item->descuento_duracion_meses) && (float)$item->descuento_valor > 0) {
                                                    $duracionTexto = " ({$item->descuento_duracion_meses} meses)";
                                                    break;
                                                }
                                            }
                                            return '-' . number_format($state, 2, ',', '.') . ' €/mes' . $duracionTexto;
                                        }
                                        return 'Sin Dto.';
                                    }),
                                
                                // DESCUENTO ÚNICO (CORRECCIÓN EN formatStateUsing)
                                \pxlrbt\FilamentExcel\Columns\Column::make('descuento_unico_total')
                                    ->heading('Descuento Único')
                                    ->formatStateUsing(fn ($state) => ((float)$state > 0) ? '-' . number_format($state, 2, ',', '.') . ' €' : 'Sin Dto.'),
                                // FIN AÑADIDO

                                // Importe Total (asumo que este es el total final con IVA)
                                \pxlrbt\FilamentExcel\Columns\Column::make('importe_total')
                                    ->heading('Importe Total Final') // Etiqueta más clara
                                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                                
                                \pxlrbt\FilamentExcel\Columns\Column::make('observaciones')
                                    ->heading('Observaciones'),
                                \pxlrbt\FilamentExcel\Columns\Column::make('created_at')
                                    ->heading('Creado en App')
                                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y H:i')),
                                \pxlrbt\FilamentExcel\Columns\Column::make('updated_at')
                                    ->heading('Actualizado en App')
                ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar Ventas Seleccionadas')
        ->modalDescription('Exportarás todos los datos de las Ventas seleccionadas.'),
                ]),
            ]);
    }

public static function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            // --- BLOQUE 1: Información General y Contexto ---
            Grid::make(3)->schema([
                // Columna izquierda
                InfoSection::make('Detalles de la Venta')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cliente.razon_social')->label('Cliente')
                            ->url(fn (Venta $record) => ClienteResource::getUrl('view', ['record' => $record->cliente_id]))->openUrlInNewTab()->icon('heroicon-m-user-circle')->color('primary')->weight('semibold')->columnSpanFull(),

                        TextEntry::make('fecha_venta')->label('Fecha de Venta')
                            ->icon('heroicon-m-calendar-days')->dateTime('d/m/Y H:i'),

                        TextEntry::make('observaciones')->label('Observaciones')
                            ->placeholder('Sin observaciones.')->columnSpanFull(),
                    ]),

                // Columna derecha
                InfoSection::make('Contexto y Estado')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('comercial.name')->label('Comercial')->badge(),
                        TextEntry::make('lead.nombre')->label('Lead de Origen')
                            ->placeholder('Venta directa.')->url(fn (Venta $record) => $record->lead_id ? LeadResource::getUrl('view', ['record' => $record->lead_id]) : null)->openUrlInNewTab()->icon('heroicon-m-link'),
                        TextEntry::make('lead.procedencia.procedencia')->label('Procedencia del Lead')
                            ->badge()->color('gray'),
                        TextEntry::make('estado_general')->label('Estado General')->badge()
                            ->state(function (Venta $record): string {
                                if ($record->suscripciones()->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)->exists()) {
                                    return 'Pendiente de Activación';
                                }
                                if ($record->suscripciones()->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)->exists()) {
                                    return 'Activa';
                                }
                                return 'Finalizada';
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Pendiente de Activación' => 'warning',
                                'Activa' => 'success',
                                default => 'gray',
                            }),
                    ]),
            ]),
            
            // --- BLOQUE 2: Resumen Económico ---
            InfoSection::make('Resumen Económico')
                ->columns(2)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('importe_base_sin_descuento')
                            ->label('Importe Original (Base)')->money('EUR')->helperText('Este es el coste real de los servicios contratados incluyendo el primer mes del servicio si es recurrente.'),
                        TextEntry::make('descuento_servicios_unicos')
                            ->label('Dto. Servicios Únicos')->money('EUR')->color('danger'),
                        TextEntry::make('importe_total')
                            ->label('Importe Final (Base)')->money('EUR')->helperText('Final sin IVA del coste de los servicios con el descuento aplicado.')->weight('bold'),
                        TextEntry::make('ahorro_total_recurrente')
                            ->label('Ahorro Total Recurrente')->money('EUR')->color('danger')->weight('bold')
                            ->helperText(function (Venta $record): ?string {
                                $descuentoMensual = $record->descuento_recurrente_mensual;
                                if ($descuentoMensual > 0) {
                                    return '(-' . number_format($descuentoMensual, 2, ',', '.') . ' €/mes)';
                                }
                                return null;
                            }),
                    ])->columnSpan(1),
                    Grid::make(1)->schema([
                         TextEntry::make('importe_total_con_iva')
                            ->label('Total a Facturar (IVA incl.)')->money('EUR')->weight('extrabold')->size('lg')->color('success'),
                    ])->columnSpan(1),
                ]),


            // --- BLOQUE 3: Desglose de Servicios Vendidos ---
            InfoSection::make('Desglose de Servicios Vendidos')
                ->schema([
                    RepeatableEntry::make('items')->label(false)->contained(false)
                        ->schema([
                            Grid::make(12)->schema([
                                TextEntry::make('nombre_final') // <-- CAMBIO CLAVE AQUÍ
                                    ->label(false)
                                    ->columnSpan(5)
                                    ->html()
                                    ->formatStateUsing(function ($state, VentaItem $record): HtmlString {
                                        // Ahora la variable $state ya contiene el nombre correcto (personalizado o el original)
                                        $nombreServicioHtml = e($state);

                                        if ($record->proyecto) {
                                            $url = \App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $record->proyecto]);
                                            $icon = \Illuminate\Support\Facades\Blade::render("<x-heroicon-s-briefcase class='h-5 w-5 text-primary-600 mr-2' />");
                                            $nombreServicioHtml = "<a href='{$url}' target='_blank' class='text-primary-600 hover:underline font-semibold flex items-center'>{$icon}" . e($state) . "</a>";
                                        }

                                        // El resto de tu lógica para mostrar el precio no necesita cambios
                                        $precioOriginal = number_format($record->precio_unitario, 2, ',', '.');
                                        $textoPVP = "PVP: {$precioOriginal} €";
                                        if ($record->servicio->tipo === \App\Enums\ServicioTipoEnum::RECURRENTE) {
                                            $periodicidad = $record->servicio->ciclo_facturacion?->value ?? '';
                                            if($periodicidad) $textoPVP .= " ({$periodicidad})";
                                        }
                                        $precioHtml = "<div class='text-xs text-gray-500'>{$textoPVP}</div>";

                                        return new HtmlString("<div>{$nombreServicioHtml}{$precioHtml}</div>");
                                    }),
                                TextEntry::make('estado_del_item')->label(false)->alignEnd()->columnSpan(3)->badge()->placeholder('---')
                                    ->state(function (VentaItem $record) {
                                        if ($record->proyecto) return $record->proyecto->estado;
                                        if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) return $record->suscripcion?->estado;
                                        return null;
                                    })
                                    ->color(fn ($state): string => match ($state) {
                                        ProyectoEstadoEnum::Pendiente => 'warning', ProyectoEstadoEnum::EnProgreso => 'primary',
                                        ProyectoEstadoEnum::Finalizado => 'success', ProyectoEstadoEnum::Cancelado => 'danger',
                                        ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                                        ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? ''),
                                TextEntry::make('descuento_info')->label(false)->alignEnd()->color('danger')->weight('semibold')->columnSpan(2)
                                    ->state(function (VentaItem $record): string {
                                        if (!$record->descuento_tipo) return '---';
                                        $valor = number_format($record->descuento_valor, 2, ',', '.');
                                        $texto = $record->descuento_tipo === 'porcentaje' ? "-{$valor}%" : "-{$valor} €";
                                        if ($record->descuento_duracion_meses) $texto .= " ({$record->descuento_duracion_meses} meses)";
                                        return $texto;
                                    }),
                                TextEntry::make('subtotal_aplicado')->label(false)->money('EUR')->weight('bold')->alignEnd()->columnSpan(2),
                            ])
                        ])
                ])
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
            'index' => Pages\ListVentas::route('/'),
            'create' => Pages\CreateVenta::route('/create'),
            'view' => Pages\ViewVenta::route('/{record}'), 
            'edit' => Pages\EditVenta::route('/{record}/edit'),
        ];
    }
}
