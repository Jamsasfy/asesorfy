<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Pages\Page;
use App\Models\Cliente; // Asegúrate de importar tu modelo Cliente
use App\Models\User; // Importamos el modelo User para obtener el usuario autenticado
use Filament\Tables; // Necesario para HasTable y sus componentes
use BezhanSalleh\FilamentShield\Traits\HasPageShield; // El trait de Filament Shield
use Illuminate\Database\Eloquent\Builder; // Necesario para el tipo de retorno de getTableQuery
use Illuminate\Support\Facades\Auth;
use App\Filament\Widgets\AsesorTotalClientesWidget; // Importa la clase del widget


class MisClientesAsignados extends Page implements Tables\Contracts\HasTable // Para poder usar una tabla
{
    use Tables\Concerns\InteractsWithTable; // Funcionalidad para la tabla
    use HasPageShield; // Para la protección de la página con Shield

    // Conservamos el icono que te generó si te gusta, o puedes cambiarlo
    protected static ?string $navigationIcon = 'icon-cliente-asignado'; // Cambié a 'user-group', pero usa el que prefieras

    // Propiedades que definimos para la página
    protected static ?string $navigationLabel = 'Mis Clientes Asignados'; // Nombre en el menú
    protected static ?string $title = 'Mis Clientes Asignados'; // Título que se muestra en la página
    protected static ?string $slug = 'mis-clientes-asignados'; // URL: tu-dominio.com/admin/mis-clientes-asignados

    // Vista Blade que se usará para esta página (ya lo tenías)
    protected static string $view = 'filament.pages.mis-clientes-asignados';

    //livewire:widget

    // Propiedades públicas para pasar datos a la vista Blade
    public int $totalMisClientes = 0;
    public int $misClientesActivos = 0;
    public int $misClientesAtencionImpago = 0; // <-- NUEVA PROPIEDAD
    public string $nombreAsesor = '';
    public int $documentosPendientesPlaceholder = 0; // Para el placeholder de documentos

    public function mount(): void
    {
        /** @var User $asesor */
        $asesor = Auth::user();

        if ($asesor) {
            $this->nombreAsesor = $asesor->name;

            $queryBaseAsesor = Cliente::where('asesor_id', $asesor->id);

            $this->totalMisClientes = (clone $queryBaseAsesor)->count();
            $this->misClientesActivos = (clone $queryBaseAsesor)->where('estado', 'activo')->count();
            // CALCULAR LA NUEVA ESTADÍSTICA
            $this->misClientesAtencionImpago = (clone $queryBaseAsesor)
                                              ->whereIn('estado', ['requiere_atencion', 'impagado'])
                                              ->count();
            $this->documentosPendientesPlaceholder = 0; // Mantenemos el placeholder
        }
    }

     // 1. DEFINIR LA CONSULTA PARA LA TABLA
    protected function getTableQuery(): Builder // El nombre correcto del método es getTableQuery
    {
        /** @var User $user */
        $user = Auth::user(); // Obtenemos el usuario autenticado
        return Cliente::query()->where('asesor_id', $user->id); // Filtramos por su ID
    }

    // 2. DEFINIR LAS COLUMNAS DE LA TABLA
    // Por ahora, una columna simple para que no dé error. Luego añadiremos más.
    protected function getTableColumns(): array
    {
        return [
             Tables\Columns\TextColumn::make('razon_social')
            ->label('Razón Social / Nombre')
            ->searchable()
            ->sortable()
            ->formatStateUsing(fn ($state, $record) => $state ?: ($record->nombre . ' ' . $record->apellidos)), // Muestra razón social o nombre y apellidos

        // O, si prefieres campos separados:
        // Tables\Columns\TextColumn::make('nombre')
        //     ->label('Nombre')
        //     ->searchable()
        //     ->sortable(),
        // Tables\Columns\TextColumn::make('apellidos')
        //     ->label('Apellidos')
        //     ->searchable()
        //     ->sortable(),

        Tables\Columns\TextColumn::make('dni_cif') // Este también parece un campo importante de tu resource
            ->label('DNI/CIF')
            ->searchable(),

        Tables\Columns\TextColumn::make('email_contacto') // Usamos el nombre correcto del campo email
             ->label('Email')
             ->searchable(),

        Tables\Columns\TextColumn::make('telefono_contacto') // Ya que lo tenemos en ClienteResource
             ->label('Teléfono')
             ->searchable(),
        ];
    }
   protected function getTableActions(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->url(fn (Cliente $record): string => ClienteResource::getUrl('view', ['record' => $record])),
            Tables\Actions\EditAction::make()
                ->url(fn (Cliente $record): string => ClienteResource::getUrl('edit', ['record' => $record])),
            // No incluimos DeleteAction aquí si la política/permisos de ClienteResource lo manejan
        ];
    }
 
       protected function getHeaderActions(): array
    {
        // Devolvemos un array vacío para no mostrar ninguna acción en la cabecera
        return [];
    }

   



   
}