<?php

namespace App\Filament\Resources\ClienteAsignadoResource\Pages;

use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\ClienteAsignadoResource;
use App\Models\Lead;
use App\Models\Procedencia;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action; // <-- Añade esta línea
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ViewClienteAsignado extends ViewRecord
{
    protected static string $resource = ClienteAsignadoResource::class;

        // *** Método para personalizar el título de la página ***
        public function getTitle(): string
        {
            // Obtiene el registro actual (el objeto Cliente)
            $cliente = $this->getRecord();
    
            // Construye el título usando el nombre del cliente
            // Usa razon_social si es el nombre principal, o tu Accessor nombre_completo
            $nombreCliente = $cliente->razon_social ?? $cliente->nombre ?? 'este cliente'; // Fallback si fuera necesario
            // O si usas el Accessor: $nombreCliente = $cliente->nombre_completo ?? 'este cliente';
    
            return 'Cliente: ' . $nombreCliente;
        }

        protected function getHeaderActions(): array
        {
             // Buscamos una sola vez el ID de la procedencia “solicitud_interna”
             $solicitudInternaId = Procedencia::where('key', 'solicitud_interna')
             ->value('id');
            return [             
                Actions\EditAction::make()
                ->label('Editar')
                ->icon('icon-customer'),    
    //crear servivicio interno que se convierte en leads del tipo servicio interno
                    Action::make('nueva_solicitud_interna')
                    ->label('Nueva solicitud interna')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->form([
                        Hidden::make('cliente_id')
                            ->default(fn ($record) => $record->getKey()),
    
                        // Usamos la clave para obtener dinámicamente el ID
                        Hidden::make('procedencia_id')
                            ->default($solicitudInternaId),
    
                             // Datos del cliente
                        Hidden::make('nombre')
                        ->label('Nombre cliente')
                        ->default(fn ($record) => $record->razon_social ?: "{$record->nombre} {$record->apellidos}"),
    
                    Hidden::make('email')
                        ->label('Email cliente')
                        ->default(fn ($record) => $record->email_contacto),
    
                    Hidden::make('tfn')
                        ->label('Teléfono cliente')
                        ->default(fn ($record) => $record->telefono_contacto),
    
                        Textarea::make('demandado')
                            ->label('Detalle de la solicitud')
                            ->rows(4)
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        Lead::create([
                            'cliente_id'     => $data['cliente_id'],
                            'procedencia_id' => $data['procedencia_id'],
                            'nombre'         => $data['nombre'],
                            'email'          => $data['email'],
                            'tfn'            => $data['tfn'],
                            'demandado'    => $data['demandado'],
                            'estado'         => LeadEstadoEnum::SIN_GESTIONAR->value,
                            'asignado_id'    => null,
                            'creado_id'     => auth()->id(),
                        ]);
    
                        Notification::make()
                            ->title('✅ Solicitud creada')
                            ->body('Se ha generado un Lead interno y está pendiente de asignación.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Crear solicitud interna')
                    ->modalSubmitActionLabel('Crear solicitud'),    
    
            ];
        }


}
