<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteAsignadoResource\Pages;
//use App\Filament\Resources\ClienteAsignadoResource\RelationManagers;
use App\Models\Cliente;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ClienteAsignadoResource\RelationManagers;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Illuminate\Support\HtmlString;


class ClienteAsignadoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'icon-cliente-asignado';
    //protected static ?string $slug = 'mis-clientes';

    protected static ?string $navigationGroup = 'Mi espacio de trabajo';

    protected static ?string $navigationLabel = 'Mis clientes asignados';
    protected static ?string $modelLabel = 'Mi cliente asignado';
    protected static ?string $pluralModelLabel = 'Mis clientes asignados';

    public static function getPermissionName(): ?string
    {
        return 'cliente_asignado'; // Este será el prefijo base para los permisos de este recurso
                                 // Por ejemplo: view_any_cliente_asignado, update_cliente_asignado
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
    ];
}

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('asesor_id', auth()->id())->count();
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('asesor_id', auth()->id());
    }
    public static function canViewAny(): bool
    {
        // Solo los asesores deberían ver este recurso en la navegación.
        return auth()->user()->hasRole('asesor');
    }


    public static function form(Form $form): Form
    {
        return $form
        ->schema([
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
                        ->maxLength(191),
    
                    TextInput::make('apellidos')
                        ->label('Apellidos')
                        ->suffixIcon('heroicon-m-identification')
                        ->maxLength(191),
    
                    TextInput::make('razon_social')
                        ->label('Razón social')
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
                        ->suffixIcon('heroicon-m-at-symbol')
                        ->email()
                        ->required()
                        ->maxLength(191),
    
                    TextInput::make('telefono_contacto')
                        ->label('Teléfono')
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
                        ->rules(['nullable', new \App\Rules\ValidIban])
                        ->required(fn ($get) => $get('estado') === 'activo')
                        ->placeholder('ES12 3456 7890 1234 5678 9012')
                        ->suffixIcon('heroicon-m-credit-card')
                        ->maxLength(34),
    
                    TextInput::make('iban_impuestos')
                        ->label('IBAN para impuestos')
                        ->rules(['nullable', new \App\Rules\ValidIban])
                        ->required(fn ($get) => $get('estado') === 'activo')
                        ->placeholder('ES12 3456 7890 1234 5678 9012')
                        ->suffixIcon('heroicon-m-currency-euro')
                        ->maxLength(34),
    
                    TextInput::make('ccc')
                        ->label('CCC')
                        ->maxLength(20),
                ])
                ->columns(3),
    
           /*  Section::make('Asignación interna')
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
    
                    Select::make('coordinador_id')
                        ->label('Coordinador')
                        ->relationship('coordinador', 'name', fn ($query) =>
                            $query->whereHas('roles', fn ($q) => $q->where('name', 'coordinador'))
                        )
                        ->preload()
                        ->searchable()
                        ->suffixIcon('heroicon-m-user-group'),
                ])
                ->columns(2), */
    
            Section::make('Estado y control')
                ->description('Gestión del estado y notas internas del cliente.')
                ->schema([
                    Select::make('estado')
                        ->label('Estado del cliente')
                        ->options([
                            'pendiente' => 'Pendiente',
                            'activo' => 'Activo',
                            'impagado' => 'Impagado',
                            'bloqueado' => 'Bloqueado',
                            'rescindido' => 'Rescindido',
                            'baja' => 'Baja',
                            'requiere_atencion' => 'Requiere atención',
                        ])
                        ->default('pendiente')
                        ->required()
                        ->live()
                        ->suffixIcon('heroicon-m-information-circle'),
    
                    DatePicker::make('fecha_alta')
                        ->label('Fecha de alta'),
    
                    DatePicker::make('fecha_baja')
                        ->label('Fecha de baja'),
    
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
                            ->color(fn (string $state): string => match ($state) {
                                'pendiente' => 'warning',
                                'activo' => 'success',
                                'impagado' => 'danger',
                                'bloqueado' => 'gray',
                                'rescindido' => 'danger',
                                'baja' => 'gray',
                                'requiere_atencion' => 'info',
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
                        TextEntry::make('fecha_alta')
                        ->label('Alta servicio')
                        ->copyable()
                        ->weight('bold')
                        ->dateTime('d/m/y - H:m')
                        ->color('primary'),        
                            
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

                    Tabs::make('Datos cliente')
                    ->tabs([
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
                        Tabs\Tab::make('Estado y control')
                            ->schema([
                                TextEntry::make('estado')                           
                                ->label('Estado')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'pendiente' => 'warning',
                                    'activo' => 'success',
                                    'impagado' => 'danger',
                                    'bloqueado' => 'gray',
                                    'rescindido' => 'danger',
                                    'baja' => 'gray',
                                    'requiere_atencion' => 'info',
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
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->recordUrl(null)   
            ->columns([
                TextColumn::make('razon_social')
                ->label('Razón Social')
                ->searchable()
                ->sortable()
                ->formatStateUsing(fn ($state) => $state ?: '-'),
        
            TextColumn::make('dni_cif')
                ->label('DNI o CIF')
                ->searchable()
                ->sortable(),
        
            TextColumn::make('nombre')
                ->label('Nombre')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        
            TextColumn::make('apellidos')
                ->label('Apellidos')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        
            TextColumn::make('tipoCliente.nombre')
                ->label('Tipo')
                ->badge()
                ->sortable(),
        
            TextColumn::make('estado')
                ->label('Estado')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'pendiente' => 'warning',
                    'activo' => 'success',
                    'impagado' => 'danger',
                    'bloqueado' => 'gray',
                    'rescindido' => 'secondary',
                    'baja' => 'gray',
                    'requiere_atencion' => 'info',
                    default => 'gray',
                })
                ->sortable(),
                
        
            TextColumn::make('provincia')
                ->label('Provincia')
                ->sortable(),
        
            TextColumn::make('localidad')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),
        
            TextColumn::make('telefono_contacto')
                ->label('Teléfono'),
        
            TextColumn::make('email_contacto')
                ->label('Email'),
        
            TextColumn::make('created_at')
                ->label('Creado en App')
                ->dateTime('d/m/y - H:i')
                ->sortable(),
        
            TextColumn::make('fecha_alta')
                ->label('Fecha de Alta')
                ->dateTime('d/m/y - H:i')
                ->sortable(),    
        
            TextColumn::make('fecha_baja')
                ->label('Fecha de Baja servicio')
                ->dateTime('d/m/y - H:i')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),
            ])
            ->filters([
           SelectFilter::make('estado')
            ->label('Estado')
            ->preload()
            ->options([
                'pendiente' => 'Pendiente',
                'activo' => 'Activo',
                'impagado' => 'Impagado',
                'bloqueado' => 'Bloqueado',
                'rescindido' => 'Rescindido',
                'baja' => 'Baja',
                'requiere_atencion' => 'Requiere atención',
            ])
            ->searchable()
            ->multiple(),

        SelectFilter::make('tipo_cliente_id')
            ->label('Tipo de cliente')
            ->relationship('tipoCliente', 'nombre')
            ->preload()
            ->searchable(),
        SelectFilter::make('provincia')
            ->label('Provincia')
            ->options(function () {
                // Asegúrate de que el archivo de config existe y tiene datos
                $provinciasConfig = config('provincias.provincias', []);
                if (empty($provinciasConfig)) {
                    return []; // Devuelve vacío si no hay configuración
                }
                // Usa array_combine para que la clave (nombre) sea tanto el valor como la etiqueta
                $keys = array_keys($provinciasConfig);
                return array_combine($keys, $keys);
            })
            ->searchable()
            ->multiple(),

        DateRangeFilter::make('fecha_alta')
            ->label('Alta APP')
            ->placeholder('Rango de fechas a buscar'),

        DateRangeFilter::make('fecha_baja')
            ->label('Baja APP')
            ->placeholder('Rango de fechas a buscar'),

    ], layout: FiltersLayout::AboveContent) // Opcional: Mantiene los filtros arriba
    ->filtersFormColumns(5) // Ajustado a 5 filtros, puedes cambiarlo
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\UsuariosRelationManager::class,
            RelationManagers\DocumentosRelationManager::class,
            RelationManagers\ComentariosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClienteAsignados::route('/'),
            'create' => Pages\CreateClienteAsignado::route('/create'),
            'view'   => Pages\ViewClienteAsignado::route('/{record}'),     // ← esta línea
            'edit' => Pages\EditClienteAsignado::route('/{record}/edit'),
        ];
    }
}
