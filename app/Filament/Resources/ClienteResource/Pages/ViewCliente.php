<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\ClienteResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use App\Filament\Resources\VentaResource;
use Filament\Actions\Action; // <-- Añade esta línea
use App\Models\Cliente; // <-- Asegúrate de importar el modelo Cliente
use App\Models\Lead;
use App\Models\Procedencia;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ViewCliente extends ViewRecord
{
    protected static string $resource = ClienteResource::class;

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
      // ******************************************************


    protected function getHeaderActions(): array
    {
         // Buscamos una sola vez el ID de la procedencia “solicitud_interna”
         $solicitudInternaId = Procedencia::where('key', 'solicitud_interna')
         ->value('id');
        return [
            Actions\EditAction::make()
            ->label('Editar')
            ->icon('icon-customer')
            ->visible(fn (ViewRecord $livewire): bool =>
            auth()->user()?->can('update', $livewire->getRecord())
        ),           
            Action::make('crear_comentario')
            ->label('Añadir Comentario')           
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('warning') // 🟠 Naranja
            ->form([
                Textarea::make('comentario')
                    ->label('Comentario')
                    ->required()
                    ->placeholder('Escribe aquí tu comentario...'),
            ])
            ->action(function ($record, array $data) {
                $record->comentarios()->create([
                    'contenido' => $data['comentario'],
                    'user_id' => auth()->id(),
                ]);
            })
            ->modalHeading('Nuevo comentario')
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar'),

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






            Action::make('cambiar_estado')
            ->label('Cambiar Estado')
            ->color('info')
            ->icon('heroicon-o-pencil')
            ->form([
                Select::make('estado')
                    ->label('Nuevo Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'activo' => 'Activo',
                        'impagado' => 'Impagado',
                        'bloqueado' => 'Bloqueado',
                        'rescindido' => 'Rescindido',
                        'baja' => 'Baja',
                        'requiere_atencion' => 'Requiere atención',
                    ])
                    ->required(),
            ])
            ->action(function ($record, array $data) {
                $record->update(['estado' => $data['estado']]);
            })
            ->modalHeading('Cambiar Estado del Cliente')
            ->modalSubmitActionLabel('Actualizar')
            ->modalCancelActionLabel('Cancelar'),

        // Acción “Asignar asesor” (sólo si no hay asesor)
        Action::make('asignar_asesor')
            ->label('Asignar asesor')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn ($record) => is_null($record->asesor_id))
            ->form([
                Select::make('asesor_id')
                    ->label('Selecciona asesor')
                    ->options(
                        User::whereHas('roles', fn($q) => $q->where('name','asesor'))
                            ->where('acceso_app', true)
                            ->pluck('name','id')
                    )
                    ->searchable()
                    ->required(),
            ])
            ->action(function ($record, array $data) {
                $record->update(['asesor_id' => $data['asesor_id']]);
                \Filament\Notifications\Notification::make()
                    ->title('✅ Asesor asignado')
                    ->body("El cliente ahora tiene al asesor {$record->asesor->name}.")
                    ->success()
                    ->send();
            })
            ->modalHeading('Asignar asesor al cliente')
            ->modalSubmitActionLabel('Asignar'),

        // Acción “Cambiar asesor” (sólo si ya hay uno)
        Action::make('cambiar_asesor')
            ->label('Cambiar asesor')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(fn ($record) => ! is_null($record->asesor_id))
            ->form([
                Select::make('asesor_id')
                    ->label('Selecciona nuevo asesor')
                    ->options(
                        User::whereHas('roles', fn($q) => $q->where('name','asesor'))
                            ->where('acceso_app', true)
                            ->pluck('name','id')
                    )
                    ->searchable()
                    ->required(),
            ])
            ->action(function ($record, array $data) {
                $record->update(['asesor_id' => $data['asesor_id']]);
                \Filament\Notifications\Notification::make()
                    ->title('🔄 Asesor cambiado')
                    ->body("Se ha reasignado al asesor {$record->asesor->name}.")
                    ->success()
                    ->send();
            })
            ->modalHeading('Cambiar asesor del cliente')
            ->modalSubmitActionLabel('Actualizar'),

               // 3) Acción para quitar asesor
        Action::make('quitar_asesor')
        ->label('Quitar asesor')
        
        ->button()
        ->color('danger')
        ->visible(fn ($record) => ! is_null($record->asesor_id))
        ->requiresConfirmation()              // pide confirmación
        ->modalHeading('¿Quitar asesor?')
        ->modalDescription('Esto dejará al cliente sin asesor asignado.')
        ->modalSubmitActionLabel('Sí, quitar')
        ->action(function ($record) {
            $record->update(['asesor_id' => null]);
            \Filament\Notifications\Notification::make()
                ->title('🗑️ Asesor quitado')
                ->body('El cliente ya no tiene asesor asignado.')
                ->danger()
                ->send();
        }),


        ];
    }


}