<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Filament\Resources\ClienteResource\RelationManagers;
use App\Models\Cliente;
use App\Models\User;
use App\Rules\NombreOrazonSocial;
use App\Rules\ValidIban;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Actions\Action as ActionInfolist;
use App\Filament\Resources\VentaResource;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Tabs;
use App\Enums\ClienteEstadoEnum;








class ClienteResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'icon-customer';
    protected static ?string $navigationGroup = 'Usuarios plataforma';
    protected static ?string $navigationLabel = 'Clientes AsesorFy';
    protected static ?string $modelLabel = 'Cliente AsesorFy';
    protected static ?string $pluralModelLabel = 'Clientes AsesorFy';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'cambiar_asesor',
            'quitar_asesor',
            'asignar_asesor',
            'asignacion_masiva_asesor',
            'cambiar_estado',
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
      /*  // *** IMPORTANTE: Este método controla si el recurso aparece en la navegación y es accesible ***
       public static function canViewAny(): bool
       {
           // Permite que administradores, supervisores Y asesores lo vean en la navegación.
           return auth()->user()->hasRole('admin') || auth()->user()->hasRole('supervisor') || auth()->user()->hasRole('asesor');
       } */


    public static function form(Form $form): Form
    {
        return $form
        ->schema([
              // 1) Ocultamos los dos IDs y los precargamos desde la query
            
              Hidden::make('comercial_id')
                ->default(fn() => auth()->id()),
              /*   Hidden::make('comercial_id')
              ->default(fn (): ?int => request()->query('comercial_id')), */

            Section::make('Datos básicos del cliente')
                ->description('Información general para identificar y clasificar al cliente.')
                ->schema([
                    Select::make('tipo_cliente_id')
                        ->label('Tipo de cliente')
                        ->relationship('tipoCliente', 'nombre')
                        ->preload()
                        ->required()
                        ->searchable()
                        ->suffixIcon('heroicon-m-adjustments-horizontal'),

                    TextInput::make('nombre')
                        ->label('Nombre')                       
                        ->suffixIcon('heroicon-m-user')
                        ->maxLength(191) ,

                    TextInput::make('apellidos')
                        ->label('Apellidos')
                        ->suffixIcon('heroicon-m-identification')                       
                        ->maxLength(191),

                    TextInput::make('razon_social')
                        ->label('Razón social')
                        ->default(request()->query('razon_social'))
                        ->suffixIcon('heroicon-m-building-office')                       
                        ->maxLength(191),

                    TextInput::make('dni_cif')
                        ->label('DNI o CIF')
                        ->suffixIcon('heroicon-m-document-text')
                        ->required()
                        ->maxLength(191),
                ])
                ->columns(2),

            Section::make('Datos de contacto')
                ->description('Teléfono, email y dirección fiscal del cliente.')
                ->schema([
                    TextInput::make('email_contacto')
                        ->label('Email')
                        ->default(request()->query('email'))
                        ->suffixIcon('heroicon-m-at-symbol')
                        ->email()
                        ->required()
                        ->maxLength(191),

                    TextInput::make('telefono_contacto')
                        ->label('Teléfono')
                        ->default(request()->query('telefono'))
                        ->tel()
                        ->suffixIcon('heroicon-m-phone')
                        ->required()
                        ->maxLength(20),

                    Textarea::make('direccion')
                        ->label('Dirección')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    TextInput::make('codigo_postal')
                        ->label('Código postal')
                        ->maxLength(10),

                    TextInput::make('localidad')
                        ->label('Localidad')
                        ->maxLength(191),

                        Select::make('provincia')
                        ->label('Provincia')
                        ->required()
                        ->options(array_combine(
                            array_keys(config('provincias.provincias')),
                            array_keys(config('provincias.provincias'))
                        ))
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $provincias = config('provincias.provincias');
                            $set('comunidad_autonoma', $provincias[$state] ?? null);
                        })
                        ->searchable()
                        ->suffixIcon('heroicon-m-map'),
                    
                    TextInput::make('comunidad_autonoma')
                        ->label('Comunidad Autónoma')
                        ->disabled()
                        ->dehydrated()
                        ->suffixIcon('heroicon-m-globe-alt'),
                ])
                ->columns(3),

            Section::make('Datos bancarios')
                ->description('Cuentas para cuotas de AsesorFy e impuestos.')
                ->schema([
                    TextInput::make('iban_asesorfy')
                    ->label('IBAN AsesorFy')
                    ->rules(['nullable', new ValidIban])                    
                    ->required(fn ($get) => $get('estado') === ClienteEstadoEnum::ACTIVO->value)                  
                    ->placeholder('ES12 3456 7890 1234 5678 9012')
                    ->suffixIcon('heroicon-m-credit-card')
                    ->maxLength(34),

                    TextInput::make('iban_impuestos')
                        ->label('IBAN para impuestos')
                        ->rules(['nullable', new ValidIban])
                        ->required(fn ($get) => $get('estado') === ClienteEstadoEnum::ACTIVO->value)                  
                    ->placeholder('ES12 3456 7890 1234 5678 9012')
                        ->suffixIcon('heroicon-m-currency-euro')
                        ->maxLength(34),

                    TextInput::make('ccc')
                        ->label('CCC')
                        ->maxLength(20),
                ])
                ->columns(3),

            Section::make('Asignación interna')
                ->description('Asigna asesor y coordinador responsable.')
                ->schema([
                    Select::make('asesor_id')
                        ->label('Asesor')
                        ->relationship('asesor', 'name', fn ($query) =>
                            $query->whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                        )
                        ->preload()
                        ->searchable()
                        ->suffixIcon('heroicon-m-user-group'),

                  
                ])
                ->columns(2)
                ->visibleOn('edit'), // 👈 solo en edición,

            Section::make('Estado y control')
                ->description('Gestión del estado y notas internas del cliente.')
                ->schema([
                    Select::make('estado')
                        ->label('Estado del cliente')
                        ->options(ClienteEstadoEnum::class) // <-- CAMBIO AQUÍ
                        ->default(ClienteEstadoEnum::PENDIENTE)
                        ->required()
                        ->live()
                        ->suffixIcon('heroicon-m-information-circle')
                        ->visibleOn('edit'),
                        

                    DatePicker::make('fecha_alta')
                        ->label('Fecha de alta')
                        ->visibleOn('edit'),
                        

                    DatePicker::make('fecha_baja')
                        ->label('Fecha de baja')
                        ->visibleOn('edit'),
                        

                    Textarea::make('observaciones')
                        ->label('Observaciones internas')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(3),
                
                        ]);
       
       
    }


    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
        ->schema([
            Grid::make(3)->schema([
                // Info básica
                InfoSection::make('Datos basicos del cliente')
                ->description('Datos basicos del cliente como nombre, denominacion, razon social y DNI o CIF')
                ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('tipoCliente.nombre')
                            ->label(new HtmlString('<span class="font-semibold">Tipo Cliente</span>'))
                            //->badge()
                            ->color('danger')
                            ->weight('bold'),
                           
    
                        TextEntry::make('nombre')
                        ->label(new HtmlString('<span class="font-semibold">Nombre</span>'))
                            ->copyable()
                            ->weight('bold')
                          
                            ->color('primary'), // <-- Ejemplo de color estático    
                        TextEntry::make('apellidos')
                        ->label(new HtmlString('<span class="font-semibold">Apellidos</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('razon_social')
                        ->label(new HtmlString('<span class="font-semibold">Razon Social</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')                          
                            ->columnSpan(2),    
                        TextEntry::make('dni_cif')
                        ->label(new HtmlString('<span class="font-semibold">DNI / CIF</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('success'),                         
                        TextEntry::make('estado')
                        ->label(new HtmlString('<span class="font-semibold">Estado</span>'))                            
                            ->badge()
                            ->color(fn (ClienteEstadoEnum $state): string => match ($state) { // <-- CAMBIO AQUÍ
                                ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                                ClienteEstadoEnum::ACTIVO => 'success',
                                ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                                ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                                default => 'gray',
                            }),
                        TextEntry::make('asesor.name')
                        ->label(new HtmlString('<span class="font-semibold">Asesor</span>'))
                        ->badge()
                        ->getStateUsing(fn ($record) =>
                            $record->asesor
                                ? $record->asesor->name
                                : '⚠️ Sin asignar'
                        )
                        ->color(fn ($state) => str_contains($state, 'Sin asignar') ? 'warning' : 'success'),   
                         
                         TextEntry::make('tarifa_principal_activa_con_precio') // <--- USA EL NUEVO ACCESOR
                        ->label('Tarifa Base')
                        ->placeholder('Ninguna')
                        ->badge() // La insignia se aplicará a toda la cadena "FYCA - 75,00 €"
                        ->tooltip(function ($record) {
                            // El tooltip puede seguir mostrando solo el nombre completo del servicio
                            if ($record->tarifa_principal_activa && $record->tarifa_principal_activa->servicio) {
                                return $record->tarifa_principal_activa->servicio->nombre;
                            }
                            return null; 
                        }),
                                            ])
                    ->columns(3)
                    ->columnSpan(1),

//seccion datos de contacto
                    InfoSection::make('Datos de contacto')
                    ->icon('heroicon-o-phone')
                    ->description('Datos de contacto que tenemos del cliente')
                    ->schema([
                        TextEntry::make('email_contacto')
                        ->label(new HtmlString('<span class="font-semibold">Email</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')
                            //->size('lg')
                            ->columnSpan(2), // <-- Ejemplo de color estático
    
                        TextEntry::make('telefono_contacto')
                        ->label(new HtmlString('<span class="font-semibold">Teléfono</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'), // <-- Ejemplo de color estático    
                        TextEntry::make('codigo_postal')
                        ->label(new HtmlString('<span class="font-semibold">Código Postal</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')
                            ->size('lg'),
                        TextEntry::make('localidad')
                        ->label(new HtmlString('<span class="font-semibold">Localidad</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),    
                        TextEntry::make('provincia')
                        ->label(new HtmlString('<span class="font-semibold">Provincia</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),                      
                        TextEntry::make('direccion')
                        ->label(new HtmlString('<span class="font-semibold">Dirección</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')                            
                            ->columnSpan(2),    
                        TextEntry::make('comunidad_autonoma')
                        ->label(new HtmlString('<span class="font-semibold">CCAA</span>'))
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),         
                                     
                    ])
                    ->columns(3)
                    ->columnSpan(1),

//seccion datos bancarios

                  

                        Tabs::make('Datos cliente')
                        ->tabs([
                             Tabs\Tab::make('Estado y control')
                            ->schema([
                                TextEntry::make('estado')                           
                                ->label('Estado')
                                ->badge()
                                 ->color(fn (ClienteEstadoEnum $state): string => match ($state) { // <-- CAMBIO 1: Acepta el Enum
                                    // CAMBIO 2: Compara con los casos del Enum, no con texto
                                    ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                                    ClienteEstadoEnum::ACTIVO => 'success',
                                    ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                                    ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                                    default => 'gray',
                                }),
                                TextEntry::make('fecha_alta')
                                ->label('Alta servicio')
                                ->copyable()
                                ->weight('bold')
                                ->dateTime('d/m/y - H:m')
                                ->color('primary'),
                                TextEntry::make('fecha_baja')
                                ->label('Baja servicio')
                                ->copyable()
                                ->weight('bold')
                                ->dateTime('d/m/y - H:m')
                                ->color('primary'),
                                TextEntry::make('created_at')
                                ->label('Creado en APP')
                                ->copyable()
                                ->weight('bold')
                                ->dateTime('d/m/y - H:m')
                                ->color('primary'),
                            ])
                            ->columns(4),

                            Tabs\Tab::make('Datos bancarios')                            
                                ->schema([
                                    TextEntry::make('iban_asesorfy')
                                    ->label(new HtmlString('<span class="font-semibold">Cuenta bancaria cuotas AsesorFy</span>'))                                
                                    ->copyable()
                                    ->weight('bold')
                                    ->state(fn ($record) => $record->iban_asesorfy ?: 'No informado')
                                    ->color(fn (string $state): string =>
                                        $state === 'No informado' ? 'danger' : 'primary'
                                    ),
                                
                                TextEntry::make('iban_impuestos')                                
                                    ->label(new HtmlString('<span class="font-semibold">Cuenta bancaria impuestos</span>')) 
                                    ->copyable()
                                    ->weight('bold')                         
                                    ->state(fn ($record) => $record->iban_impuestos ?: 'No informado')
                                    ->color(fn (string $state): string =>
                                        $state === 'No informado' ? 'danger' : 'primary'
                                    ), 
                                TextEntry::make('ccc')                                
                                    ->label(new HtmlString('<span class="font-semibold">Codigo CCC</span>'))
                                    ->copyable()
                                    ->weight('bold')
                                    ->state(fn ($record) => $record->ccc ?: 'No informado')
                                    ->color(fn (string $state): string =>
                                        $state === 'No informado' ? 'danger' : 'primary'
                                    ),
                                ])->columns(1)
                                ->columnSpan(1),
                       
                            
                        Tabs\Tab::make('Observaciones generales cliente')
                            ->schema([
                                TextEntry::make('observaciones')
                                ->label('Observaciones internas')
                                ->columnSpanFull(),
                            ]),
                    ])
                    ]), 
        ]);
    }





    public static function table(Table $table): Table
    {
        return $table
        ->striped()
        ->recordUrl(null)   
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->columns([
             TextColumn::make('razon_social')
                ->label('Razón Social')
                ->searchable(isIndividual: true)
                ->sortable()
                ->formatStateUsing(fn ($state) => $state ?: '-'),

            TextColumn::make('dni_cif')
                ->label('DNI o CIF')
                ->searchable(isIndividual: true)
                ->sortable(),
              

             TextColumn::make('nombre')
                ->label('Nombre')
                ->searchable(isIndividual: true)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('apellidos')
                ->label('Apellidos')
                ->searchable(isIndividual: true)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),


            TextColumn::make('tipoCliente.nombre')
                ->label('Tipo')
                ->badge()
                ->sortable(),

            TextColumn::make('estado')
                ->label('Estado')
                ->badge()
                ->color(fn (ClienteEstadoEnum $state): string => match ($state) { // <-- CAMBIO AQUÍ
                    ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                    ClienteEstadoEnum::ACTIVO => 'success',
                    ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                    ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                    default => 'gray',
                })
                ->sortable(),

              // Nueva columna para la Tarifa Principal Activa (nombre del servicio)
             // Columna Modificada para la Tarifa Principal Activa
           TextColumn::make('tarifa_principal_activa_con_precio')
                ->label('Tarifa Principal')
                ->placeholder('Ninguna')
                ->badge()
                ->color(function ($record): string {
                    // Si el cliente tiene una tarifa principal activa...
                    if ($record->tarifa_principal_activa) {
                        return 'success'; // ...el color es verde.
                    }
                    // Si no la tiene...
                    return 'warning'; // ...el color es naranja/amarillo.
                })
                ->tooltip(function ($record) {
                    if ($record->tarifa_principal_activa && $record->tarifa_principal_activa->servicio) {
                        return $record->tarifa_principal_activa->servicio->nombre;
                    }
                    return null;
                })
                ->searchable(false)
                ->sortable(false),

            TextColumn::make('provincia')
                ->label('Provincia')
                ->sortable(),

            TextColumn::make('localidad')
            ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),

            TextColumn::make('telefono_contacto')
                ->label('Teléfono')
                ->searchable(isIndividual: true),

            TextColumn::make('email_contacto')
                ->label('Email')
                ->searchable(isIndividual: true),

                TextColumn::make('asesor.name')
                ->label('Asesor')
                ->badge()
                ->getStateUsing(fn ($record) =>
                    $record->asesor
                        ? $record->asesor->name
                        : '⚠️ Sin asignar'
                )
                ->color(fn ($state) => str_contains($state, 'Sin asignar') ? 'warning' : 'success'),

            TextColumn::make('created_at')
                ->label('Creado en App')
                 ->toggleable(isToggledHiddenByDefault: true)
                ->dateTime('d/m/y - H:m')
               
                ->sortable(),

            TextColumn::make('fecha_alta')
                ->label('Fecha de Alta')
                 ->toggleable(isToggledHiddenByDefault: true)
                ->dateTime('d/m/y - H:m')
               
                ->sortable(),    
               
            TextColumn::make('fecha_baja')
                ->label('Fecha de Baja servicio')
                ->dateTime('d/m/y - H:m')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),        
            ])
            ->recordUrl(null)
        ->filters([
            SelectFilter::make('estado')
                ->label('Estado')
                ->preload()
                ->options(ClienteEstadoEnum::class) // <-- CAMBIO AQUÍ
                ->searchable(),
            SelectFilter::make('tipo_cliente_id')
                ->label('Tipo de cliente')
                ->relationship('tipoCliente', 'nombre')
                ->preload()
                ->searchable(),

            SelectFilter::make('provincia')
                ->label('Provincia')
                ->options(array_keys(config('provincias.provincias')))
                ->searchable(),
                SelectFilter::make('asesor_id')
                ->label('Asesor asignado')
                ->options(
                    \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                        ->where('acceso_app', true)
                        ->get()
                        ->pluck('full_name', 'id')
                )
                ->searchable()
                ->preload(),
            DateRangeFilter::make('fecha_alta')
                ->label('Alta APP')
                ->placeholder('Rango de fechas a buscar'),  
            DateRangeFilter::make('fecha_baja')
                ->label('Baja APP')
                ->placeholder('Rango de fechas a buscar'),      
            Filter::make('sin_asesor')
                ->label('Sin asesor asignado')
                ->query(fn ($query) => $query->whereNull('asesor_id'))
                ->toggle(),      

                ],layout: FiltersLayout::AboveContent)
                ->filtersFormColumns(7)
        ->actions([
            Tables\Actions\ViewAction::make()
            ->label('')
            ->tooltip('Ver cliente'),
            Tables\Actions\EditAction::make()
            ->label('')
            ->tooltip('Editar cliente'),
            ActionsAction::make('cambiarAsesor')
            
                ->label('')
                ->tooltip('Cambiar asesor del cliente')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn ($record) =>
                !empty($record->asesor_id) &&
                auth()->user()?->can('cambiar_asesor_cliente')
            )
                ->form([
                    Select::make('asesor_id')
                        ->label('Selecciona nuevo asesor')
                        ->options(
                            \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                ->where('acceso_app', true)
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $record->asesor_id = $data['asesor_id'];
                    $record->save();

                    \Filament\Notifications\Notification::make()
                        ->title('🔄 Asesor actualizado')
                        ->body('El asesor del cliente ha sido cambiado correctamente.')
                        ->success()
                        ->send();
                })
                ->modalHeading('Cambiar asesor del cliente')
                ->modalSubmitActionLabel('Actualizar'),
                
            ActionsAction::make('quitarAsesor')
                ->label('')
                ->tooltip('Quitar asesor del cliente')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->visible(fn ($record) =>
                !empty($record->asesor_id) &&
                auth()->user()?->hasPermissionTo('quitar_asesor_cliente')
                )
                ->requiresConfirmation()
                ->modalHeading('¿Seguro que quieres quitar el asesor?')
                ->modalDescription('El cliente quedará sin asesor asignado.')
                ->modalSubmitActionLabel('Sí, quitar asesor')
                ->action(function ($record) {
                    $record->asesor_id = null;
                    $record->save();
            
                    \Filament\Notifications\Notification::make()
                        ->title('🗑️ Asesor eliminado')
                        ->body('El asesor ha sido desvinculado del cliente correctamente.')
                        ->danger()
                        ->send();
                }),
                
            ActionsAction::make('asignarAsesor')
                ->label('')
                ->tooltip('Asignar asesor al cliente')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->visible(fn ($record) =>
                empty($record->asesor_id) &&
                auth()->user()?->hasPermissionTo('asignar_asesor_cliente')
                )
                ->form([
                    Select::make('asesor_id')
                        ->label('Selecciona asesor')
                        ->options(
                            \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                ->where('acceso_app', true)
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->modalHeading('Asignar asesor al cliente')
                ->modalSubmitActionLabel('Asignar')
                ->action(function ($record, array $data) {
                    $record->asesor_id = $data['asesor_id'];
                    $record->save();
            
                    \Filament\Notifications\Notification::make()
                        ->title('✅ Asesor asignado')
                        ->body('El asesor ha sido asignado correctamente al cliente.')
                        ->success()
                        ->send();
                }),   
              
        ])
        ->bulkActions([
          

//grupo de asignaciones masivas
            BulkActionGroup::make([
               
                Tables\Actions\BulkAction::make('asignar_asesor')
                ->icon('heroicon-o-user-group')
                ->label('Asignación masiva asesor')
                ->form([
                    Select::make('asesor_id')
                        ->label('Seleccionar asesor')
                        ->options(
                            User::where('acceso_app', true)
                            ->whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                            ->pluck('name', 'id')
                        )
                        ->required()
                        ->preload()
                        ->searchable(),
                ])
                ->action(function (array $data, EloquentCollection $records) {
                    $asesorId = $data['asesor_id'];
                    $ids = $records->pluck('id')->toArray();
    
                    Cliente::whereIn('id', $ids)->update(['asesor_id' => $asesorId]);
                })
                ->requiresConfirmation()
                ->modalHeading('Asignar asesor a los clientes seleccionados')
                ->modalDescription('Estás a punto de asignar masivamente un asesor. ¿Estás seguro?')
                ->modalSubmitActionLabel('Sí, asignar todos')
                ->modalIcon('heroicon-o-user-group')
                ->color('warning')
                ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('quitarAsesor')
                    ->label('Quitar asesor masivo')
                    ->icon('heroicon-m-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar asesor')
                    ->modalDescription('Esto quitará el asesor asignado a los clientes seleccionados. ¿Seguro?')
                    ->modalSubmitActionLabel('Quitar asesor')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records) {
                        foreach ($records as $record) {
                            if (!is_null($record->asesor_id)) {
                                $record->update(['asesor_id' => null]);
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('✅ Asesores eliminados')
                            ->body('Los asesores fueron eliminados correctamente de los clientes seleccionados.')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\BulkAction::make('cambiarAsesor')
                    ->label('Cambiar asesor')
                    ->icon('heroicon-m-arrow-path-rounded-square')
                    ->color('primary')
                    ->form([
                        Select::make('asesor_id')
                            ->label('Selecciona nuevo asesor')
                            ->options(
                                \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                    ->where('acceso_app', true)
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->modalHeading('Cambiar asesor')
                    ->modalDescription('Este cambio afectará a todos los clientes seleccionados. ¿Deseas continuar?')
                    ->modalSubmitActionLabel('Cambiar asesor')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (array $data, \Illuminate\Support\Collection $records) {
                        foreach ($records as $record) {
                            $record->update(['asesor_id' => $data['asesor_id']]);
                        }
                
                        \Filament\Notifications\Notification::make()
                            ->title('✅ Asesores actualizados')
                            ->body('Se ha actualizado el asesor de los clientes seleccionados correctamente.')
                            ->success()
                            ->send();
                    }),
            ]) ->label('🧑‍💼 Gestión de Asesores')
            ->visible(fn () => auth()->user()?->hasPermissionTo('asignacion_masiva_asesor_cliente')), // 👈 Aplica a todo el grupo


           
            BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('clientes')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                    \pxlrbt\FilamentExcel\Columns\Column::make('id'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('tipocliente.nombre')
                       ->heading('Tipo cliente'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('razon_social')
                        ->heading('Razón Social'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('dni_cif')
                        ->heading('DNI o CIF'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('email_contacto')
                        ->heading('Email'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('telefono_contacto')
                        ->heading('Teléfono'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('direccion')
                        ->heading('Dirección'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('codigo_postal')
                        ->heading('Código Postal'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('localidad')
                        ->heading('Localidad'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('provincia')
                        ->heading('Provincia'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('comunidad_autonoma')
                        ->heading('Comunidad Autónoma'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('estado')
                        ->heading('Estado'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_alta')
                        ->heading('Fecha de Alta')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),

                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_baja')
                        ->heading('Fecha de Baja')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),

                    \pxlrbt\FilamentExcel\Columns\Column::make('iban_asesorfy')
                        ->heading('IBAN AsesorFy'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('iban_impuestos')
                        ->heading('IBAN Impuestos'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('ccc')
                        ->heading('CCC'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('asesor.name')
                        ->heading('Asesor')
                        ->getStateUsing(fn ($record) =>
                            $record->asesor
                                ? $record->asesor->name
                                : '⚠️ Sin asignar'
                        ),
                   \pxlrbt\FilamentExcel\Columns\Column::make('coordinador') // Usamos un nombre genérico
                    ->heading('Coordinador')
                    ->getStateUsing(function (Cliente $record): string {
                        // Seguimos la cadena de relaciones para encontrar el nombre del coordinador
                        $coordinadorName = $record->asesor?->trabajador?->departamento?->coordinador?->name;

                        // Si lo encontramos, lo devolvemos. Si no, 'Sin asignar'.
                        return $coordinadorName ?? '⚠️ Sin asignar';
                    }),
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
        ->modalHeading('Exportar clientes')
        ->modalDescription('Exportarás todos los datos de clientes seleccionados.'),
               
            ])->label('Otras acciones'),
        ]);
    }

    public static function getRelations(): array
{
    return [
        Clienteresource\RelationManagers\ComentariosRelationManager::class,
       Clienteresource\RelationManagers\DocumentosRelationManager::class,
       Clienteresource\RelationManagers\UsuariosRelationManager::class,
       RelationManagers\LeadsRelationManager::class,
        RelationManagers\SuscripcionesRelationManager::class,

         
      
        // Puedes añadir más relation managers aquí si es necesario
    ];
}
//RelationManagers\PostsRelationManager::class,
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'view'   => Pages\ViewCliente::route('/{record}'),     // ← esta línea
            'edit' => Pages\EditCliente::route('/{record}/edit'),
       

        ];
    }
}
