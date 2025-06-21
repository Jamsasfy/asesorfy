<?php

namespace App\Filament\Resources;

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
use Filament\Tables\Enums\FiltersLayout;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

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
                            ->suffixIcon('heroicon-m-user'),
                        
                        Select::make('lead_id')
                            ->label('Lead de Origen')
                            ->relationship('lead', 'nombre')
                            ->nullable(false) // Campo obligatorio
                            ->required() // Es obligatorio
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
                            ->default(fn() => auth()->id())
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
                                    ->relationship('servicio', 'nombre')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
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

                                Forms\Components\Hidden::make('precio_unitario_aplicado') 
                                    ->dehydrated(true), 
                                
                                Forms\Components\Hidden::make('subtotal_aplicado') 
                                    ->dehydrated(true), 
                                
                                Forms\Components\Hidden::make('subtotal_aplicado_con_iva') 
                                    ->dehydrated(true), 


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
                               ->required()
                               ->visible(function (Get $get) {
        $servicioId = $get('servicio_id');
        if (! $servicioId) return false;

        $servicio = \App\Models\Servicio::find($servicioId);
        if (! $servicio || $servicio->tipo->value !== 'recurrente') {
            return false;
        }

        // Buscar si hay algún otro item con servicio que requiere proyecto
        $todosLosItems = $get('../../items') ?? [];

        foreach ($todosLosItems as $index => $item) {
            if ($get("../../items.{$index}.servicio_id") == $servicioId) {
                continue; // saltar el propio item
            }

            $servicioRelacionado = \App\Models\Servicio::find($item['servicio_id'] ?? null);
            if ($servicioRelacionado && $servicioRelacionado->requiere_proyecto_activacion) {
                return false; // Hay otro item que requiere proyecto => no mostrar el campo
            }
        }

        // No hay proyectos => sí mostrar el campo y hacerlo obligatorio
        return true;
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
                                                $set('descuento_valor', null); // Esto ya pone el valor en null en el formulario
                                                $set('descuento_duracion_meses', null); // <<< AÑADIDO: Limpiar también la duración
                                                $set('descuento_valido_hasta', null); // <<< AÑADIDO: Limpiar también la fecha
                                                $set('observaciones_descuento', null); // <<< AÑADIDO: Limpiar también la descripción
                                                self::updateTotals($get, $set);
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
                                            
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),
                                           

                                        TextInput::make('descuento_duracion_meses')
                                            ->label('Duración (meses)')
                                            ->numeric()->type('text')->inputMode('numeric')
                                            ->nullable()
                                            ->columnSpan(1)
                                            ->live()
                                            ->visible(function (Get $get): bool {
                                                if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                                                    return false;
                                                }
                                                // <<< CORRECCIÓN AQUI: $item->servicio->tipo->value === 'recurrente' (sin el namespace completo delante)
                                                return Servicio::find($servicioId)?->tipo?->value === 'recurrente'; 
                                            })
                                            ->dehydrated(true)
                                            
                                          ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            if (empty($state) || !is_numeric($state)) {
                                                $set('descuento_valido_hasta', null);
                                                self::updateTotals($get, $set);
                                                return;
                                            }

                                            $fechaInicio = $get('fecha_inicio_servicio');

                                            if (! $fechaInicio) {
                                                $set('descuento_valido_hasta', null);
                                                self::updateTotals($get, $set);
                                                return;
                                            }

                                            $fechaFinDescuento = \Carbon\Carbon::parse($fechaInicio)
                                                ->addMonths((int)$state - 1)
                                                ->endOfMonth()
                                                ->format('Y-m-d');

                                            $set('descuento_valido_hasta', $fechaFinDescuento);
                                            self::updateTotals($get, $set);
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
                                                // <<< CORRECCIÓN AQUI: $item->servicio->tipo->value === 'recurrente' (sin el namespace completo delante)
                                                return Servicio::find($servicioId)?->tipo?->value === 'recurrente'; 
                                            })
                                            ->dehydrated(true)
                                           ,
                                          

                                            
                                        Textarea::make('observaciones_descuento') 
                                            ->label('Descripción del Descuento')
                                            ->nullable()
                                            ->columnSpan(3)
                                            ->visible(fn (Get $get) => !empty($get('descuento_tipo')))
                                            ->dehydrated(true)
                                          ,


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
                                    ])->columns(12)->columnSpanFull(),
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
            'edit' => Pages\EditVenta::route('/{record}/edit'),
        ];
    }
}
