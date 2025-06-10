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
            
                Forms\Components\Hidden::make('recalculate_trigger')
                    ->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $totales = self::calculateGrandTotal($get);
                        $set('importe_total_sin_iva', $totales['total_sin_iva']);
                        $set('importe_total',         $totales['total_con_iva']);
                    })
                    ->dehydrated(false),
            /** ───────────────────
             * 2) DATOS DE LA VENTA
             * ─────────────────── */
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
                        ->suffixIcon('heroicon-m-user'),

                    Select::make('lead_id')
                        ->label('Lead de Origen')
                        ->relationship('lead', 'nombre')
                        ->nullable()
                        ->searchable()
                        ->default(fn () => request()->query('lead_id'))
                        ->preload()
                        ->suffixIcon('heroicon-m-identification'),

                    Select::make('user_id')
                        ->label('Comercial')
                        ->relationship('comercial', 'name')
                        ->default(Auth::id())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->suffixIcon('heroicon-m-briefcase'),

                    DateTimePicker::make('fecha_venta')
                        ->label('Fecha de Venta')
                        ->native(false)
                        ->required()
                        ->default(now()),

                    TextInput::make('importe_total')
                        ->label('Importe Total (con IVA)')
                        ->suffix('€')
                        ->readOnly()
                       ->default(0),
                      

                    TextInput::make('importe_total_sin_iva')
                        ->label('Importe Total (sin IVA)')
                        ->suffix('€')
                        ->readOnly()
                      ->default(0),
                      

                    Textarea::make('observaciones')
                        ->label('Observaciones de la Venta')
                        ->nullable()
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            /** ─────────────────────
             * 3) ÍTEMS DE LA VENTA
             * ───────────────────── */
            Section::make('Items de la Venta')
                ->description('Añade los servicios incluidos en esta venta.')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->schema([
                            /* ── 3.1 Servicio ───────────────────────────── */
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

                                    // 1) Obtener el modelo (o null)
                                    $servicio = $state ? Servicio::find($state) : null;

                                    // 2) Rellenar precio_unitario
                                    $set('precio_unitario', $servicio?->precio_base ?? 0);

                                    // 3) Actualizar subtotales del ítem + totales globales
                                    self::updateTotals($get, $set);
$set('../../../recalculate_trigger', now()->timestamp);                                      
                             }),

                            /* ── 3.2 Cantidad ───────────────────────────── */
                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()->type('text')->inputMode('numeric')
                                ->required()
                                ->default(1)
                                ->minValue(1)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::updateTotals($get, $set);

                                }),

                            /* ── 3.3 Precio unitario ────────────────────── */
                            TextInput::make('precio_unitario')
                                ->label('Precio (€)')
                                ->helperText('Sin IVA.')
                                ->numeric()->type('text')->inputMode('decimal')
                                ->required()
                                ->suffix('€')
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::updateTotals($get, $set);
$set('../../../recalculate_trigger', now()->timestamp);                                      
                                }),

                            /* ── 3.4 Subtotales readonly ────────────────── */
                            TextInput::make('subtotal')
                                ->label('Subtotal (€)')
                                ->helperText('Sin IVA.')
                                ->numeric()->readOnly()->suffix('€'),

                            TextInput::make('subtotal_con_iva')
                                ->label('Subtotal con IVA')
                                ->numeric()->readOnly()->suffix('€'),

                            /* ── 3.5 Fechas opcionales ──────────────────── */
                            DatePicker::make('fecha_inicio_servicio')
                                ->label('Inicio Servicio (recurrente)')
                                ->native(false)
                                ->nullable()
                                ->columnSpan(2),

                            /* ── 3.6 Observaciones item ─────────────────── */
                            Textarea::make('observaciones_item')
                                ->label('Notas del servicio')
                                ->nullable()
                                ->rows(1)
                                ->columnSpan(4),

                            /* ── 3.7 Descuentos (colapsable) ────────────── */
                            Section::make('Aplicar Descuento')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Select::make('descuento_tipo')
                                        ->label('Tipo de Descuento')
                                        ->placeholder('Sin descuento')
                                        ->options([
                                            'porcentaje'   => 'Porcentaje (%)',
                                            'fijo'         => 'Cantidad Fija (€)',
                                            'precio_final' => 'Precio Final (€)',
                                        ])
                                        ->nullable()
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            $set('descuento_valor', null); // reset
                                            self::updateTotals($get, $set);
$set('../../../recalculate_trigger', now()->timestamp);                                      
                                     }),

                                    TextInput::make('descuento_valor')
                                        ->label('Valor del Descuento')
                                        ->numeric()->type('text')->inputMode('decimal')
                                        ->nullable()
                                        ->live()
                                        ->visible(fn (Get $get) => filled($get('descuento_tipo')))
                                        ->suffix(fn (Get $get): ?string => match ($get('descuento_tipo')) {
                                            'porcentaje'   => '%',
                                            default        => '€',
                                        })
                                        ->helperText(fn (Get $get): ?string => match ($get('descuento_tipo')) {
                                            'porcentaje'   => 'Introduce solo el número (ej. 50).',
                                            'fijo'         => 'Cantidad a descontar.',
                                            'precio_final' => 'Nuevo precio final.',
                                            default        => null,
                                        })
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::updateTotals($get, $set);
$set('../../../recalculate_trigger', now()->timestamp);                                      
                           }),

                                    TextInput::make('descuento_duracion_meses')
                                        ->label('Duración (meses)')
                                        ->numeric()->type('text')->inputMode('numeric')
                                        ->nullable()
                                        ->live()
                                        ->visible(fn (Get $get) =>
                                            filled($get('descuento_tipo')) &&
                                            optional(Servicio::find($get('servicio_id')))->tipo?->value === 'recurrente'
                                        )
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            // Calcula fecha fin descuento
                                            if (blank($state) || !is_numeric($state)) {
                                                $set('descuento_valido_hasta', null);
                                            } else {
                                                $base = $get('fecha_inicio_servicio') ?? $get('../../fecha_venta');
                                                if ($base) {
                                                    $hasta = now()->parse($base)
                                                                  ->addMonths((int)$state - 1)
                                                                  ->endOfMonth()
                                                                  ->format('Y-m-d');
                                                    $set('descuento_valido_hasta', $hasta);
                                                }
                                            }
                                            self::updateTotals($get, $set);
$set('../../../recalculate_trigger', now()->timestamp);                                      
  }),

                                    DatePicker::make('descuento_valido_hasta')
                                        ->label('Descuento Válido Hasta')
                                        ->native(false)
                                        ->nullable()
                                        ->readOnly()
                                        ->placeholder('Se calcula automáticamente')
                                        ->visible(fn (Get $get) =>
                                            filled($get('descuento_tipo')) &&
                                            optional(Servicio::find($get('servicio_id')))->tipo?->value === 'recurrente'
                                        ),

                                    TextInput::make('descuento_descripcion')
                                        ->label('Descripción del Descuento')
                                        ->nullable()
                                        ->visible(fn (Get $get) => filled($get('descuento_tipo'))),

                                    /* Totales con descuento */
                                    TextInput::make('precio_final_calculado')
                                        ->label('Precio Final con Dto.')
                                        ->numeric()->readOnly()
                                        ->helperText('Sin IVA.')
                                        ->suffix('€')
                                        ->visible(fn (Get $get) => filled($get('descuento_tipo'))),

                                    TextInput::make('precio_final_con_iva')
                                        ->label('Total con IVA')
                                        ->numeric()->readOnly()
                                        ->suffix('€')
                                        ->visible(fn (Get $get) => filled($get('descuento_tipo'))),
                                ])->columns(12)->columnSpanFull(),
                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->reorderable(true)
                        ->collapsible()
                        ->cloneable()
                        ->minItems(1)
                        ->addActionLabel('Añadir Servicio')
                        ->live()   // Repeater reactivo
                  ->afterStateUpdated(function (Get $get, Set $set) {
                // cada vez que cambia algo dentro del repeater recalculamos
                $totales = self::calculateGrandTotal($get);
                $set('importe_total_sin_iva', $totales['total_sin_iva']);
                $set('importe_total',         $totales['total_con_iva']);
                 $set('../../../recalculate_trigger', now()->timestamp);   // ⇐ tres “../”
                            }),
                    ])
                ->columnSpanFull(),
        ]);
}


  // Función para calcular los totales de cada ítem del Repeater
    private static function updateTotals(Get $get, Set $set): void
    {
        // 1. Recalcular el subtotal (sin IVA)
        $cantidad = $get('cantidad') ?? 1;
        $precioUnitario = $get('precio_unitario') ?? 0;
        $subtotal = 0;
        if (is_numeric($cantidad) && is_numeric($precioUnitario)) {
            // Redondeamos el resultado del subtotal a 2 decimales
            $subtotal = round($cantidad * $precioUnitario, 2);
        }
        $set('subtotal', $subtotal);

        // 2. Calcular el Subtotal con IVA para el ítem
        // OBTENEMOS EL IVA DESDE EL SERVICIO DE CONFIGURACIÓN
        // La variable 'IVA_general' en la DB debe ser un decimal (ej. 0.21)
        $ivaPorcentaje = ConfiguracionService::get('IVA_general', 0.21);
        
        // Redondeamos el resultado a 2 decimales
        $subtotalConIva = round($subtotal * (1 + $ivaPorcentaje), 2);
        $set('subtotal_con_iva', $subtotalConIva);

        // 3. Recalcular el precio final con descuento (antes de IVA)
        $descuentoTipo = $get('descuento_tipo');
        $descuentoValor = $get('descuento_valor') ?? 0;
        $precioFinalConDto = $subtotal; // Por defecto, es el subtotal sin descuento

        if ($descuentoTipo && is_numeric($descuentoValor) && $descuentoValor > 0) {
            switch ($descuentoTipo) {
                case 'porcentaje':
                    // Redondeamos el resultado a 2 decimales
                    $precioFinalConDto = round($subtotal - ($subtotal * ($descuentoValor / 100)), 2);
                    break;
                case 'fijo':
                    // Redondeamos el resultado a 2 decimales
                    $precioFinalConDto = round($subtotal - $descuentoValor, 2);
                    break;
                case 'precio_final':
                    // Aquí el valor ya es un precio, asumimos que tiene 2 decimales
                    $precioFinalConDto = round($descuentoValor, 2);
                    break;
            }
        }
        
        $precioFinalConDto = max(0, $precioFinalConDto); // Asegura que el precio no sea negativo
        $set('precio_final_calculado', $precioFinalConDto);

        // 4. Calcular y establecer el precio final con IVA y descuentos
        // Usa el valor de la DB (0.21) directamente
        $precioConIvaYDto = round($precioFinalConDto * (1 + $ivaPorcentaje), 2);
        $set('precio_final_con_iva', $precioConIvaYDto);
    }

   /**
     * Calcula los importes totales (sin IVA y con IVA) de la venta
     * sumando los subtotales con descuento de todos los ítems.
     *
     * @param Get $get El objeto Get de Filament para obtener datos del formulario.
     * @return array Un array con 'total_sin_iva' y 'total_con_iva'.
     */
    private static function calculateGrandTotal(Get $get): array
{
    // Llamada desde el Hidden  →  un nivel arriba están los items
    $items = $get('../items') ?? [];

    $iva = (float) ConfiguracionService::get('IVA_general', 0.21);
    $totalSinIva = 0.0;

    foreach ($items as $item) {
        $cantidad = (float) ($item['cantidad'] ?? 0);
        $precio   = (float) ($item['precio_unitario'] ?? 0);
        $base     = $cantidad * $precio;

        $tipo  = $item['descuento_tipo']   ?? null;
        $valor = (float) ($item['descuento_valor'] ?? 0);

        $base = match ($tipo) {
            'porcentaje'   => max(0, $base - $base * ($valor / 100)),
            'fijo'         => max(0, $base - $valor),
            'precio_final' => max(0, $valor),
            default        => $base,
        };

        $totalSinIva += $base;
    }

    return [
        'total_sin_iva' => round($totalSinIva, 2),
        'total_con_iva' => round($totalSinIva * (1 + $iva), 2),
    ];
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
                
         

       // 2) Importe “Único” sumando subtotal donde servicio.tipo = 'unico'
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

       // 3) Importe “Recurrente”
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                ->label('Venta actualizada')
                ->dateTime('d/m/y - H:m')
                    ->sortable(),
                   
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
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
                    \pxlrbt\FilamentExcel\Columns\Column::make('id'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('cliente.razon_social')
                        ->heading('Cliente'),                   
                    \pxlrbt\FilamentExcel\Columns\Column::make('lead_id')
                        ->heading('Lead asociado'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('comercial.full_name')
                        ->heading('Vendido por'),
                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_venta')
                        ->heading('Fecha de venta')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('importe_total')
                        ->heading('Importe total')
                        ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),                       
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
