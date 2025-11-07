<?php

namespace App\Filament\Resources;

use App\Enums\EstadoRegistroFactura;
use App\Enums\TipoNifProveedor;
use App\Enums\TipoOperacionProveedor;
use App\Enums\TipoRegistroFactura;
use App\Filament\Resources\RegistroFacturaResource\Pages;
use App\Filament\Resources\RegistroFacturaResource\RelationManagers;
use App\Models\Cliente;
use App\Models\CuentaCatalogo;
use App\Models\CuentaCliente;
use App\Models\ProductoServicioBase;
use App\Models\ProductoServicioCliente;
use App\Models\Proveedor;
use App\Models\RegistroFactura;
use App\Models\RetencionIrpf;
use App\Models\SerieFacturaProveedor;
use App\Models\Tercero;
use App\Models\TipoIva;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile; // <-- MUY IMPORTANTE AÑADIR ESTE
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Log;


use Filament\Support\Enums\Alignment;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;

class RegistroFacturaResource extends Resource
{
    protected static ?string $model = RegistroFactura::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Subida y Visor de Justificante Factura')
            
                ->schema([
                    Grid::make(5)->schema([
                        Group::make()->columnSpan(3)->schema([
                           Placeholder::make('visor_final')
    ->hiddenLabel()
    ->content(function (Get $get, $record): HtmlString {
        // Obtenemos la URL (para previsualización o para un registro existente)
        $url = $get('vista_previa_url');
        if (!$url && $record && $record->justificante_path) {
            $url = route('file.view', ['path' => $record->justificante_path]);
        }
        
        if (!$url) {
            return new HtmlString('<div style="text-align: center; padding: 2rem; border: 1px dashed #666;">Selecciona un archivo para previsualizarlo aquí.</div>');
        }
        
        // Obtenemos la extensión del archivo en minúsculas
        $pathForExtension = $record?->justificante_path ?? parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($pathForExtension, PATHINFO_EXTENSION));

        // ✅ Usamos if/elseif/else en lugar de match
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return new HtmlString("<img src='{$url}' style='width: 100%; border-radius: 5px;'>");
        } elseif ($ext === 'pdf') {
            return new HtmlString("<iframe src='{$url}' style='width: 100%; height: 75vh; border: 1px solid #444; border-radius: 5px;'></iframe>");
        } else {
            return new HtmlString("<div style='padding: 2rem; text-align: center; border: 1px dashed #666;'><a href='{$url}' target='_blank'>Ver documento</a></div>");
        }
    }),
                        ]),
                        Group::make()->columnSpan(2)->schema([
                            Hidden::make('tipo')->default('recibida'),
                            Hidden::make('vista_previa_url')->reactive(),
                            FileUpload::make('justificante_path')
                                ->label('Seleccionar Factura de Gasto')
                                ->disk('public')
                                ->directory('justificantes_facturas_temp')
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set) {
                                if ($state instanceof UploadedFile) {
                                    $path = $state->store('justificantes_facturas_temp', 'public');
                                    // ✅ Usa la nueva ruta para generar la URL
                                    $set('vista_previa_url', route('file.view', ['path' => $path]));
                                }
                            }),
                       
                        ]),
                    ]),
                ]),

            Section::make('Datos del Cliente')
                ->schema([
                    Select::make('cliente_id')
                        ->label('Selecciona el Cliente')
                        ->options(fn () => Cliente::pluck('razon_social', 'id'))
                        ->searchable()->required()->reactive(),
                ]),

            Section::make('Datos del Proveedor')
                ->visible(fn (Get $get) => !empty($get('cliente_id')))
                ->schema([
                    Grid::make(5)->extraAttributes(['class' => 'bg-green-500'])->schema([
                        Select::make('proveedor_id')
                            ->label('Proveedor')
                            ->helperText('Puedes crear uno nuevo desde NEW.')
                            ->options(function (Get $get): array {
                                $clienteId = $get('cliente_id');
                                if (!$clienteId) { return []; }
                                return Proveedor::where('cliente_id', $clienteId)->orderBy('nombre')->pluck('nombre', 'id')->all();
                            })
                            ->searchable()->preload()->required()->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $proveedor = Proveedor::find($state);
                                $set('proveedor_nif', $proveedor?->nif);
                            })
                            // ✅ LÓGICA FUNCIONAL: Inyecta el cliente_id al abrir el modal
                            ->createOptionAction(function (Action $action) {
                                return $action
                                    ->label('➕ Crear nuevo proveedor')
                                    ->icon('icon-new')->color('primary')
                                    ->mountUsing(function (Form $form, Get $get) {
                                         $form->fill([
                'modal_form_cliente_id' => $get('cliente_id'),
                'cuenta_contable_id' => '__CREAR_NUEVA__', // <-- AÑADIR ESTA LÍNEA
            ]);
                                    });
                            })
                            ->createOptionForm([
                                Hidden::make('modal_form_cliente_id'),
                                Section::make('Datos Necesarios')->columns(2)->schema([
                                    TextInput::make('nombre')->required()->columnSpan(1),
                                    TextInput::make('codigo_postal')->label('Código Postal')->required()->columnSpan(1),
                                    Grid::make(3)->columnSpanFull()->schema([
                                        Select::make('tipo_nif')->label('Tipo DNI / CIF')->options(TipoNifProveedor::class)->required()->reactive()->columnSpan(1),
                                        Select::make('prefijo_intracomunitario')->label('Prefijo')->placeholder('Prefijo')->options(fn (): array => collect(config('vies', []))->map(fn ($name, $code) => "$code - $name")->all())->visible(fn (Get $get) => $get('tipo_nif') === TipoNifProveedor::INTRACOMUNITARIO->value)->required(fn (Get $get) => $get('tipo_nif') === TipoNifProveedor::INTRACOMUNITARIO->value)->columnSpan(1),
                                        TextInput::make('nif')->label('DNI / CIF')->placeholder('Número')->required()->columnSpan(fn (Get $get) => $get('tipo_nif') === TipoNifProveedor::INTRACOMUNITARIO->value ? 1 : 2),
                                    ]),
                                ]),
                                Section::make('Datos Opcionales')->columns(2)->schema([
                                    Select::make('pais')->label('País')->options(config('paises', []))->searchable()->default('ES')->reactive(),
                                    Select::make('provincia')->label('Provincia')->options(function (): array { $provincias = config('provincias.provincias', []); $nombres = array_keys($provincias); return array_combine($nombres, $nombres); })->searchable()->visible(fn (Get $get) => $get('pais') === 'ES'),
                                    TextInput::make('provincia')->label('Provincia / Estado / Región')->visible(fn (Get $get) => $get('pais') !== 'ES'),
                                    TextInput::make('poblacion')->label('Población'),
                                    TextInput::make('direccion')->columnSpanFull(),
                                    TextInput::make('persona_contacto'),
                                    TextInput::make('telefono')->tel(),
                                    TextInput::make('email')->email(),
                                    TextInput::make('email_secundario')->email(),
                                    Forms\Components\Checkbox::make('tambien_cliente')->columnSpanFull(),
                                ]),
                                Section::make('Tipos de Operaciones')->schema([
                                    Select::make('tipo_operacion')->label('Tipo Operación Proveedor')->options(TipoOperacionProveedor::class)->required(),
                                    // ✅ LÓGICA FUNCIONAL: Lee el cliente_id desde el campo oculto
                                    Select::make('cuenta_contable_id')
                                        ->label('Cuenta Contable del Proveedor')
                                        ->options(function (Get $get): array {
                                            $clienteId = $get('modal_form_cliente_id');
                                            if (!$clienteId) { return []; }
                                            $options = [];
                                            $cuentaDefault = CuentaCatalogo::where('codigo', '400000000000')->first();
                                            if ($cuentaDefault) { $options["catalogo-{$cuentaDefault->id}"] = "{$cuentaDefault->codigo} - {$cuentaDefault->descripcion}"; }
                                            $cuentasCliente = CuentaCliente::where('cliente_id', $clienteId)->where('codigo', 'like', '400%')->get();
                                            foreach ($cuentasCliente as $cuenta) { $options[$cuenta->id] = "{$cuenta->codigo} - {$cuenta->descripcion}"; }
                                            return collect($options)->prepend('Cuenta por defecto (creará una nueva para este proveedor)', '__CREAR_NUEVA__')->all();
                                        })
                                        ->default('__CREAR_NUEVA__')->searchable()->preload(),
                                ]),
                            ])
                            // ✅ LÓGICA FUNCIONAL: Crea la cuenta contable si es necesario antes de crear el proveedor
                           ->createOptionUsing(function (array $data, Get $get): string {
                                $clienteId = $get('cliente_id');
                                $data['cliente_id'] = $clienteId;

                                // Comprueba si se ha dejado la opción por defecto para crear la cuenta
                                if (isset($data['cuenta_contable_id']) && $data['cuenta_contable_id'] === '__CREAR_NUEVA__') {
                                    
                                    // Genera el siguiente código disponible
                                    $nuevoCodigo = CuentaCliente::generarSiguienteCodigoCombinado('400000000000', $clienteId);

                                    // ✅ CORRECCIÓN: Se crea la descripción con el formato que pides
                                    $descripcionNuevaCuenta = 'Proveedor - ' . $data['nombre'];
                                     // 1. Buscamos la cuenta principal en el catálogo
                                     $cuentaCatalogoPadre = CuentaCatalogo::where('codigo', '400000000000')->first();

                                    // Se crea la nueva cuenta con la descripción correcta
                                    $nuevaCuenta = CuentaCliente::create([
                                        'cliente_id' => $clienteId,
                                        'codigo' => $nuevoCodigo,
                                        'descripcion' => $descripcionNuevaCuenta, // <-- Se usa la nueva descripción
                                        'cuenta_catalogo_id' => $cuentaCatalogoPadre?->id,
                                    ]);
                                    
                                    // Se reemplaza el valor especial por el ID de la cuenta recién creada
                                    $data['cuenta_contable_id'] = $nuevaCuenta->id;
                                }
                                
                                // Finalmente, se crea el proveedor con todos los datos correctos
                                $proveedor = Proveedor::create($data);
                                return $proveedor->id;
                            }),

                        TextInput::make('proveedor_nif')->label('DNI / CIF')->disabled(),
                        DatePicker::make('fecha_expedicion')->label('Fecha')->required(),
                        Select::make('serie')
                            ->helperText('Puedes crear una serie nueva para este proveedor desde NEW.')
                            ->label('Serie')
                            ->options(function (Get $get): array {
                                $proveedorId = $get('proveedor_id');
                                if (!$proveedorId) { return []; }
                                return SerieFacturaProveedor::where('proveedor_id', $proveedorId)->where('activo', true)->pluck('descripcion', 'codigo')->all();
                            })
                            ->searchable()->preload()->required()
                            ->createOptionForm([
                                TextInput::make('codigo')->label('Código Serie (ej: A, B)')->required()->maxLength(255),
                                TextInput::make('descripcion')->label('Descripción (ej: Facturas Nacionales)')->required()->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, Get $get): string {
                                $data['cliente_id'] = $get('cliente_id');
                                $data['proveedor_id'] = $get('proveedor_id');
                                $serie = SerieFacturaProveedor::create($data);
                                return $serie->codigo;
                            })
                            ->createOptionAction(function (Action $action) {
                                return $action->label('➕ Crear nueva serie')->icon('icon-new')->color('primary');
                            }),
                        TextInput::make('numero_factura')->label('Número')->required(),
                    ]),
                ]),
//rpeater de lineas de factura de gasto

Section::make('Líneas de Factura')->extraAttributes(['class' => 'p-2'])
    ->visible(fn (Get $get) => filled($get('proveedor_id')))
    ->schema([
        Repeater::make('lineas')
            ->relationship('lineas') // relación en RegistroFactura modelo, ajusta si otro nombre
            ->columns(24)
            ->defaultItems(1)
            ->schema([
                Grid::make(24)->schema([
                    // Producto / Servicio unificado (base + cliente)
                    Select::make('producto_servicio_id')
                        ->label('Producto / Servicio')
                        ->options(function () {
                            $base = ProductoServicioBase::all()->mapWithKeys(fn($p) => ["base-{$p->id}" => $p->nombre])->toArray();
                            $cliente = ProductoServicioCliente::all()->mapWithKeys(fn($p) => ["cliente-{$p->id}" => $p->nombre])->toArray();
                            return $base + $cliente;
                        })
                        ->searchable()
                        ->columnSpan(3)
                        ->reactive()
                        ->required(),

                    TextInput::make('descripcion')
                        ->label('Descripción')
                        ->columnSpan(3)
                        ->required(),

                    Select::make('tipo_operacion')
                        ->label('Tipo Operación Proveedor')
                        ->options(fn () => collect(TipoOperacionProveedor::cases())
                            ->mapWithKeys(fn ($tipo) => [
                                $tipo->value => $tipo->getLabel(),
                            ])
                            ->toArray()
                        )
                        ->columnSpan(3)
                        ->required(),

                   Select::make('cuenta_contable')
                            ->label('Cuenta Contable')
                           ->options(function (Get $get): array {
                                $options = [];

                                // 1. Cargar las cuentas base del catálogo general
                                $cuentasBase = CuentaCatalogo::where('tipo', '!=', 'ingreso')
                                    ->where('codigo', 'not like', '400%')
                                    ->get();
                                
                                foreach ($cuentasBase as $cuenta) {
                                    $options["catalogo-{$cuenta->id}"] = "{$cuenta->codigo} - {$cuenta->descripcion}";
                                }
                                
                                // 2. Cargar las cuentas específicas del cliente
                                $clienteId = $get('../../cliente_id');
                                if ($clienteId) {
                                    $cuentasCliente = CuentaCliente::where('cliente_id', $clienteId)
                                        ->where('codigo', 'not like', '400%')
                                        ->whereHas('cuentaCatalogo', function ($query) {
                                            $query->where('tipo', '!=', 'ingreso');
                                        })
                                        ->get();
                                    
                                    foreach ($cuentasCliente as $cuenta) {
                                        $options["cliente-{$cuenta->id}"] = "{$cuenta->codigo} - {$cuenta->descripcion}";
                                    }
                                }
                                
                                // 3. Devolver el array combinado
                                return $options;
                            })
   
                            ->required()
//                           ->searchable()
                            ->columnSpan(3),
                           //->preload(),

                 /*    TextInput::make('cuenta_contable_descripcion')
                        ->label('Descripción Cuenta Contable')
                        ->columnSpan(4)
                        ->disabled(), */

                    TextInput::make('cantidad')
                        ->label('Cantidad')
                        ->numeric()
                          //  ->extraInputAttributes(['inputmode' => 'decimal', 'pattern' => '[0-9]*', 'style' => '-moz-appearance: textfield; -webkit-appearance: none;'])

                        ->columnSpan(1)
                        ->required(),

                    TextInput::make('precio_unitario')
                        ->label('Precio Unitario')
                        ->numeric()
                        ->columnSpan(2)
                        ->required(),

                    TextInput::make('descuento_valor')
                        ->label('Dto %')
                        ->numeric()
                        ->columnSpan(1),

                    TextInput::make('importe')
                        ->label('Importe')
                        ->numeric()
                        ->columnSpan(2)
                        ->disabled(),

                    Select::make('porcentaje_iva')
                    ->label('% IVA')
                  
     ->options(fn () => TipoIva::where('activo', true)
        ->orderBy('id')  // para mantener orden
        ->get()
        ->mapWithKeys(function ($iva) {
            $label = $iva->recargo_equivalencia > 0
                ? "{$iva->porcentaje}% + {$iva->recargo_equivalencia}%"
                : "{$iva->porcentaje}%";
            return [$iva->id => $label]; // Clave única por id
        })
        ->toArray())
                    //->searchable()
                    ->required()
                    ->columnSpan(2),

                Select::make('porcentaje_retencion_irpf')
                    ->label('IRPF %')
                    ->options(fn () => RetencionIrpf::where('activo', true)
                        ->pluck('porcentaje', 'porcentaje')->toArray())
                  //  ->searchable()
                    ->required()
                    ->columnSpan(2),

                    TextInput::make('total')
                        ->label('Total')
                        ->numeric()
                        ->columnSpan(2)
                        ->disabled(),
                ]),
            ])
            ->dehydrated() // Para que se guarde
            ->disableLabel()
          //  ->orderable()
            ->createItemButtonLabel('Añadir línea'),
    ]),





        ]);
}


    public static function table(Table $table): Table
    {
       return $table
        ->columns([
            // Columna para el cliente, permite buscar por nombre y ordenar
            Tables\Columns\TextColumn::make('cliente.razon_social')
                ->searchable()
                ->sortable(),

            // Número de factura con búsqueda
            Tables\Columns\TextColumn::make('numero_factura')
                ->searchable(),

            // El tipo de registro (Emitida/Recibida) con un badge de color para diferenciar
         Tables\Columns\TextColumn::make('tipo')
                ->badge()
                ->color(fn (TipoRegistroFactura $state): string => match ($state) {
                    TipoRegistroFactura::EMITIDA => 'success',
                    TipoRegistroFactura::RECIBIDA => 'warning',
                })
                ->searchable(),

            // Nombre del tercero (proveedor/cliente final)
            Tables\Columns\TextColumn::make('tercero_nombre')
                ->label('Tercero')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto para no saturar

            // Total de la factura con formato de moneda y ordenable
            Tables\Columns\TextColumn::make('total_factura')
                ->money('EUR')
                ->sortable(),

            // Fecha con formato y ordenable
            Tables\Columns\TextColumn::make('fecha_expedicion')
                ->date()
                ->sortable(),
                
            // Columnas autocalculadas, también ordenables
            Tables\Columns\TextColumn::make('ejercicio')
                ->sortable(),
            Tables\Columns\TextColumn::make('trimestre')
                ->sortable(),
        ])
        ->filters([
            // Filtro para ver solo emitidas o recibidas
            Tables\Filters\SelectFilter::make('tipo')
                ->options([
                    'emitida' => 'Emitida (Ingreso)',
                    'recibida' => 'Recibida (Gasto)',
                ]),

            // Filtro para el Ejercicio (Año)
            Tables\Filters\SelectFilter::make('ejercicio')
                ->options(
                    // Obtenemos los años únicos de la tabla para no mostrar años vacíos
                    \App\Models\RegistroFactura::query()->select('ejercicio')->distinct()->pluck('ejercicio', 'ejercicio')->all()
                ),

            // Filtro para los Trimestres
            Tables\Filters\SelectFilter::make('trimestre')
                ->options([
                    '1T' => '1er Trimestre',
                    '2T' => '2º Trimestre',
                    '3T' => '3er Trimestre',
                    '4T' => '4º Trimestre',
                ]),
            // Filtro para buscar por cliente desde un desplegable
            Tables\Filters\SelectFilter::make('cliente')
                ->relationship('cliente', 'nombre')
                ->searchable(),
        ], layout: FiltersLayout::AboveContent)
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListRegistroFacturas::route('/'),
            'create' => Pages\CreateRegistroFactura::route('/create'),
            'edit' => Pages\EditRegistroFactura::route('/{record}/edit'),
        ];
    }
}
