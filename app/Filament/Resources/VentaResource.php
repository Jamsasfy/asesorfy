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
                        // *** CORRECCIÓN CON COLUMNA REAL 'razon_social' ***
                        // Apuntamos directamente a la columna real 'razon_social' para la consulta y display
                        ->relationship('cliente', 'razon_social') // <-- Usamos 'razon_social' como columna real
                        // Ya NO necesitamos getOptionLabelFromRecordUsing si solo mostramos la columna real
                        // *** FIN CORRECCIÓN ***
                        ->required()
                        ->default(fn () => request()->query('cliente_id'))

                        ->searchable() // Buscará en la columna 'razon_social'
                        ->preload()
                        ->columnSpan(1)
                        // Opcional: Si vienes redirigido desde un Lead, pre-rellena - esto iría aquí si lo necesitas
                        // ->default(fn ($livewire) => $livewire->getRequest()->query('cliente_id'))
                        ->suffixIcon('heroicon-m-user'),
                    


                        // Campo Lead de origen: Relación nullable
                        Select::make('lead_id')
                            ->label('Lead de Origen')
                            ->relationship('lead', 'nombre') // Asume que el campo 'nombre' del Lead es suficiente
                            ->nullable()
                            ->searchable()
                            ->default(fn () => request()->query('lead_id'))
                            ->preload()
                            ->columnSpan(1)
                            // Opcional: Si vienes redirigido desde un Lead, pre-rellena
                            // ->default(fn ($livewire) => $livewire->getRequest()->query('lead_id'))
                            ->suffixIcon('heroicon-m-identification'),


                        // Campo Comercial (User): Asigna al usuario actual por defecto
                        Select::make('user_id')
                            ->label('Comercial')
                            ->relationship('comercial', 'name') // O 'full_name' si usas accesor
                            ->default(Auth::id()) // Asigna al usuario logueado
                            ->required() // Generalmente el comercial es obligatorio
                            //->default(fn () => request()->query('comercial_id'))
                            ->default(fn() => auth()->id())
                            ->searchable()
                            ->preload()
                            ->columnSpan(1)
                            ->suffixIcon('heroicon-m-briefcase'),


                        // Fecha de la Venta: Default a hoy y hora actual
                        DateTimePicker::make('fecha_venta')
                            ->label('Fecha de Venta')
                            ->native(false) // Para mejor UI
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        // Importe Total: Campo de solo lectura, se calcula por hooks
                        TextInput::make('importe_total')
                            ->label('Importe Total')
                            ->suffix('€') // O la moneda que uses
                            ->readOnly() // No se edita manualmente
                            ->disabled() // Visualmente deshabilitado
                            ->dehydrated(false) // No incluir en los datos que se guardan directamente
                            ->columnSpan(1),

                        // Observaciones generales de la Venta
                        Textarea::make('observaciones')
                            ->label('Observaciones de la Venta')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(), // Ocupa todo el ancho

                    ]),

                // --- SECCIÓN DE ITEMS DE VENTA CON REPEATER ---
                Section::make('Items de la Venta')
                     ->description('Añade los servicios incluidos en esta venta.')
                     ->schema([
                        // El Repeater se vincula a la relación 'items' (HasMany en modelo Venta)
                        

// En app/Filament/Resources/VentaResource.php

// ... dentro de public static function form(Form $form): Form
// ... dentro de Section::make('Items de la Venta')->schema([ ...

Repeater::make('items')
    ->relationship('items')
    ->schema([
        // --- FILA 1 DE CAMPOS DEL ITEM ---
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
                // Siempre actualizamos todos los totales al cambiar el servicio
                self::updateTotals($get, $set);
            }),

        TextInput::make('cantidad')
            ->label('Cantidad')
            ->numeric()->type('text')->inputMode('numeric')
            ->required()
            ->default(1)
            ->minValue(1)
            ->live()
            ->columnSpan(1) // Ajustado para un mejor layout
            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

        TextInput::make('precio_unitario')
            ->label('Precio (€)')
            ->helperText('Sin IVA.')
            ->numeric()->type('text')->inputMode('decimal') // <-- Sin flechas
            ->required()
            ->suffix('€')
            ->columnSpan(1) // Ajustado
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

        TextInput::make('subtotal')
            ->label('Subtotal (€)')
            ->helperText('Sin IVA.')
            ->numeric()->type('text') // <-- Sin flechas
            ->readOnly()
            ->suffix('€')
            ->columnSpan(1), // Ajustado
         // --- NUEVO CAMPO: SUBTOTAL CON IVA ---
        TextInput::make('subtotal_con_iva')
            ->label('Subtotal con IVA')
            ->numeric()->type('text') // <-- Sin flechas
            ->readOnly()->suffix('€')
            ->columnSpan(1),    

        // --- FILA 2 DE CAMPOS DEL ITEM ---
        DatePicker::make('fecha_inicio_servicio')
            ->label('Inicio Servicio (si es recurrente)')
            ->native(false)
            ->nullable()
            ->columnSpan(2), // Ocupa un tercio de la fila

        Textarea::make('observaciones_item')
            ->label('Notas del servicio')
            ->nullable()
            ->rows(1)
            ->columnSpan(4), // Ocupa dos tercios

        // --- SECCIÓN DE DESCUENTOS COMPLETA Y FUNCIONAL ---
        Section::make('Aplicar Descuento')
            ->collapsible()
            ->collapsed()
            ->schema([
                Select::make('descuento_tipo')
                    ->label('Tipo de Descuento')
                    ->placeholder('Sin descuento') // <-- MEJORA 1: Placeholder más claro
                    ->options([
                        'porcentaje' => 'Porcentaje (%)',
                        'fijo'       => 'Cantidad Fija (€)',
                        'precio_final' => 'Precio Final (€)',
                    ])
                    ->nullable()
                    ->live()
                    ->columnSpan(2)
                    ->afterStateUpdated(function(Get $get, Set $set) {
                        $set('descuento_valor', null);
                        self::updateTotals($get, $set);
                    }),

                TextInput::make('descuento_valor')
                    ->label('Valor del Descuento')
                    ->numeric()
                    ->type('text') // <-- LA SOLUCIÓN: Renderiza como un campo de texto normal, sin flechas.
                    ->inputMode('decimal') // <-- MEJORA UX: Sugiere el teclado numérico en móviles.
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
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                TextInput::make('descuento_duracion_meses')
                    ->label('Duración (meses)')
                    ->numeric()->type('text')->inputMode('numeric') // <-- Sin flechas
                    ->nullable()
                     ->columnSpan(1)
                    ->live()
                   ->visible(function (Get $get): bool {
                        // Debe existir un tipo de descuento Y el servicio debe ser recurrente
                        if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                            return false;
                        }
                        return Servicio::find($servicioId)?->tipo?->value === 'recurrente';
                    })
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                        if (empty($state) || !is_numeric($state)) {
                            $set('descuento_valido_hasta', null);
                            return;
                        }
                        $fechaInicioBase = $get('fecha_inicio_servicio') ?? $get('../../fecha_venta');
                        if ($fechaInicioBase) {
                            $fechaFinDescuento = now()->parse($fechaInicioBase)
                                                       ->addMonths((int)$state - 1)
                                                       ->endOfMonth()
                                                       ->format('Y-m-d');
                            $set('descuento_valido_hasta', $fechaFinDescuento);
                        }
                    }),

                DatePicker::make('descuento_valido_hasta')
                    ->label('Descuento Válido Hasta')
                    ->native(false)
                    ->nullable()
                    ->readOnly()
                     ->columnSpan(2)
                    ->placeholder('Se calcula automáticamente')
                    ->visible(function (Get $get): bool {
                        // Misma lógica que el campo de duración
                        if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                            return false;
                        }
                        return Servicio::find($servicioId)?->tipo?->value === 'recurrente';
                    }),
                    
                TextInput::make('descuento_descripcion')
                    ->label('Descripción del Descuento')
                    ->nullable()
                     ->columnSpan(3)
                    ->visible(fn (Get $get) => !empty($get('descuento_tipo'))),

                TextInput::make('precio_final_calculado')
                    ->label('Precio Final con Dto.')
                    ->numeric()->type('text') // <-- Sin flechas
                    ->readOnly()
                    ->helperText('Sin IVA.')
                    ->columnSpan(1)
                    //->prefix('Total:')
                    ->suffix('€')
                    ->visible(fn (Get $get) => !empty($get('descuento_tipo'))),

                    // --- NUEVO CAMPO PARA MOSTRAR EL PRECIO CON IVA ---
                TextInput::make('precio_final_con_iva')
                    ->label('Total con IVA')
                    ->numeric()->type('text') // <-- Sin flechas
                    //->helperText('Precio final con IVA incluido.')
                    ->readOnly()
                     ->visible(fn (Get $get) => !empty($get('descuento_tipo')))
                   // ->prefix('P.V.P:')
                    ->suffix('€')
                     ->columnSpan(1),


            ])->columns(12)->columnSpanFull(), // La sección de descuento ocupa todo el ancho del item

    ])
    ->columns(12) // Cada item del repeater se organiza en una rejilla de 12 columnas.
    ->defaultItems(1)
    ->reorderable(true)
    ->collapsible()
    ->cloneable()
    ->minItems(1)
    ->addActionLabel('Añadir Servicio'), // Texto del botón para añadir item
                            ])
                     ->columnSpanFull(), // La sección de items ocupa todo el ancho


            ]);
    }

 private static function updateTotals(Get $get, Set $set): void
{
    // 1. Recalcular el subtotal
    $cantidad = $get('cantidad') ?? 1;
    $precioUnitario = $get('precio_unitario') ?? 0;
    $subtotal = 0;
    if (is_numeric($cantidad) && is_numeric($precioUnitario)) {
        // Redondeamos el resultado del subtotal
        $subtotal = round($cantidad * $precioUnitario, 2);
    }
    $set('subtotal', $subtotal);

    // 2. Calcular el Subtotal con IVA
    $ivaPorcentaje = config('asesorfy.iva_general', 21);
    // Redondeamos el resultado
    $subtotalConIva = round($subtotal * (1 + ($ivaPorcentaje / 100)), 2);
    $set('subtotal_con_iva', $subtotalConIva);

    // 3. Recalcular el precio final con descuento
    $descuentoTipo = $get('descuento_tipo');
    $descuentoValor = $get('descuento_valor') ?? 0;
    $precioFinalConDto = $subtotal;

    if ($descuentoTipo && is_numeric($descuentoValor) && $descuentoValor > 0) {
        switch ($descuentoTipo) {
            case 'porcentaje':
                // Redondeamos el resultado
                $precioFinalConDto = round($subtotal - ($subtotal * ($descuentoValor / 100)), 2);
                break;
            case 'fijo':
                // Redondeamos el resultado
                $precioFinalConDto = round($subtotal - $descuentoValor, 2);
                break;
            case 'precio_final':
                // Aquí el valor ya es un precio, asumimos que tiene 2 decimales
                $precioFinalConDto = round($descuentoValor, 2);
                break;
        }
    }
    
    $precioFinalConDto = max(0, $precioFinalConDto);
    $set('precio_final_calculado', $precioFinalConDto);

    // 4. Calcular y establecer el precio final con IVA y descuentos
    // Redondeamos el resultado final
    $precioConIvaYDto = round($precioFinalConDto * (1 + ($ivaPorcentaje / 100)), 2);
    $set('precio_final_con_iva', $precioConIvaYDto);
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
