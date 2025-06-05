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
                        Repeater::make('items')
                            ->relationship('items') // <-- Nombre de la relación en el modelo Venta (hasMany VentaItem)
                            ->schema([
                                // Campos para cada VentaItem
                                Select::make('servicio_id')
                                    ->label('Servicio')
                                    ->relationship('servicio', 'nombre') // FK a Servicio existente
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->distinct() // Evita seleccionar el mismo servicio más de una vez en la misma venta
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems() // Otra forma de evitar duplicados
                                    ->live() // Crucial para actualizar precio/subtotal reactivamente
                                    ->columnSpan(4)
                                    // --- Lógica para pre-rellenar precio al seleccionar servicio ---
                                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                        // Si se selecciona un servicio ($state es el ID del servicio)
                                        if ($state) {
                                            $servicio = Servicio::find($state);
                                            if ($servicio) {
                                                // Establece el precio unitario y recalcula subtotal
                                                $set('precio_unitario', $servicio->precio_base);
                                                $cantidad = $get('cantidad') ?? 1; // Usa la cantidad actual o 1 por defecto
                                                $set('subtotal', $cantidad * $servicio->precio_base);
                                                 // Si tienes fecha_inicio_servicio en items, podrías setear default aquí si es recurrente
                                                 // if ($servicio->tipo === 'recurrente') { $set('fecha_inicio_servicio', now()->startOfDay()); }
                                            } else {
                                                 // Limpiar campos si no se encuentra el servicio (raro con constrained FK)
                                                 $set('precio_unitario', 0);
                                                 $set('subtotal', 0);
                                            }
                                        } else {
                                             // Limpiar campos si se deselecciona el servicio
                                            $set('precio_unitario', 0);
                                            $set('subtotal', 0);
                                        }
                                    }),
                                    // *************************************************************


                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live() // Crucial para recalcular subtotal
                                    ->columnSpan(1)
                                    // --- Lógica para recalcular subtotal al cambiar cantidad ---
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $cantidad = $get('cantidad');
                                        $precioUnitario = $get('precio_unitario');
                                        if (is_numeric($cantidad) && is_numeric($precioUnitario)) {
                                            $set('subtotal', $cantidad * $precioUnitario);
                                        } else {
                                            $set('subtotal', 0);
                                        }
                                    }),
                                    // ***********************************************************

                                TextInput::make('precio_unitario')
                                    ->label('Precio (€)')
                                    ->numeric()
                                    ->required()
                                    ->suffix('€')
                                    ->columnSpan(2)
                                    // --- Lógica para recalcular subtotal al cambiar precio unitario ---
                                    ->live() // Crucial para recalcular subtotal
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $cantidad = $get('cantidad') ?? 0; // Usa cantidad o 0
                                        $precioUnitario = $get('precio_unitario');
                                        if (is_numeric($cantidad) && is_numeric($precioUnitario)) {
                                            $set('subtotal', $cantidad * $precioUnitario);
                                        } else {
                                            $set('subtotal', 0);
                                        }
                                    }),
                                    // *****************************************************************


                                TextInput::make('subtotal')
                                    ->label('Subtotal (€)')
                                    ->numeric()
                                    ->readOnly() // No se edita manualmente
                                  //  ->disabled() // Visualmente deshabilitado
                                   // ->dehydrated(false) // No se incluye en los datos que se guardan (se calcula al guardar VentaItem)
                                    ->suffix('€')
                                    ->columnSpan(2),

                                    DatePicker::make('fecha_inicio_servicio')
                                    ->label('Inicio Servicio (si recurrente)')
                                    ->native(false)
                                    ->nullable()
                                    ->columnSpan(2)
                                    // Opcional: Hacer visible/required condicionalmente si el servicio seleccionado es 'recurrente'
                                    // Requiere lógica en el Repeater y en el modelo VentaItem para acceder al tipo del servicio relacionado
                                    // ->visible(fn (Get $get) => $get('servicio_id') ? (Servicio::find($get('servicio_id'))?->tipo === 'recurrente') : false)
                                    // ->required(fn (Get $get) => $get('servicio_id') ? (Servicio::find($get('servicio_id'))?->tipo === 'recurrente') : false)
                                    // Esta lógica con visible/required en Repeater items puede ser compleja y requerir 'live' en el Select del Servicio
                                    // Quizás sea mejor dejarlo siempre visible/nullable y añadir una validación simple en el action/hook.
                                    ,



                                Textarea::make('observaciones_item')
                                    ->label('Notas del Item')
                                    ->nullable()
                                    ->rows(2)
                                    ->columnSpan(5), // Ocupa el resto del ancho en la fila de items

                                 // Campo opcional para fecha de inicio de servicio recurrente
                               


                            ])
                            ->columns(16) // Columnas dentro de cada item del Repeater
                            ->defaultItems(1) // Empieza con un item vacío por defecto
                            ->reorderable(true) // Permitir cambiar el orden de los items
                            ->collapsible() // Permitir colapsar items individuales
                            ->cloneable() // Permitir duplicar items
                            ->minItems(1) // O 0 si permites ventas sin items
                            ->addActionLabel('Añadir Servicio'), // Texto del botón para añadir item
                            ])
                     ->columnSpanFull(), // La sección de items ocupa todo el ancho


            ]);
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
