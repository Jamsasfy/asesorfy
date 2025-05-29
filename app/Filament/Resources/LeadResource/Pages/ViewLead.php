<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    /**
     * El texto que aparece en la cabecera de la página.
     */
    public function getHeading(): string
    {
        $lead = $this->getRecord();

        // Fecha de referencia: cierre o ahora
        $end = $lead->fecha_cierre
            ? Carbon::parse($lead->fecha_cierre)
            : Carbon::now();

        // Calculamos diff (2 unidades, sin prefijos)
        $diff = Carbon::parse($lead->created_at)
            ->diffForHumans($end, [
                'parts'  => 2,
                'short'  => true,
                'syntax' => Carbon::DIFF_ABSOLUTE,
            ]);

        // Devolvemos texto plano: Vista Lead #ID (2d 3h)
        return "Vista Lead #{$lead->id}, vida del leads desde creación ({$diff})";
    }



    protected function getHeaderActions(): array
    {

         // Preparamos el array [value => label] de la enum
    $estadoOptions = [];
    foreach (LeadEstadoEnum::cases() as $case) {
        $estadoOptions[$case->value] = $case->getLabel();
    }
    
        return [
            Actions\EditAction::make(),

             // 1) Asignar comercial si NO tiene asignado
             Action::make('asignar_comercial')
             ->label('Asignar Comercial')
             ->icon('heroicon-o-user-plus')
             ->color('success')
             ->visible(fn ($record) => is_null($record->asignado_id))
             ->form([
                 Select::make('asignado_id')
                     ->label('Elige Comercial')
                     ->options(
                         User::whereHas('roles', fn($q) => $q->where('name','comercial'))
                             ->pluck('name','id')
                     )
                     ->searchable()
                     ->required(),
             ])
             ->action(function ($record, array $data) {
                 $record->update(['asignado_id' => $data['asignado_id']]);
                 \Filament\Notifications\Notification::make()
                     ->title('✅ Comercial asignado')
                     ->body("Lead asignado a {$record->asignado->name}.")
                     ->success()
                     ->send();
             })
             ->modalHeading('Asignar Comercial al Lead')
             ->modalSubmitActionLabel('Asignar'),

         // 2) Cambiar comercial si ya tiene uno
         Action::make('cambiar_comercial')
             ->label('Cambiar Comercial')
             ->icon('heroicon-o-user-minus')
             ->color('primary')
             ->visible(fn ($record) => ! is_null($record->asignado_id))
             ->form([
                 Select::make('asignado_id')
                     ->label('Nuevo Comercial')
                     ->options(
                         User::whereHas('roles', fn($q) => $q->where('name','comercial'))
                             ->pluck('name','id')
                     )
                     ->searchable()
                     ->required(),
             ])
             ->action(function ($record, array $data) {
                 $record->update(['asignado_id' => $data['asignado_id']]);
                 \Filament\Notifications\Notification::make()
                     ->title('🔄 Comercial cambiado')
                     ->body("Ahora asignado a {$record->asignado->name}.")
                     ->success()
                     ->send();
             })
             ->modalHeading('Cambiar Comercial del Lead')
             ->modalSubmitActionLabel('Cambiar'),

         // 3) Quitar comercial si tiene uno
         Action::make('quitar_comercial')
             ->label('Quitar Comercial')
             ->icon('heroicon-o-user-minus')
             ->color('danger')
             ->visible(fn ($record) => ! is_null($record->asignado_id))
             ->requiresConfirmation()
             ->modalHeading('¿Quitar comercial?')
             ->modalDescription('Esto dejará el lead sin comercial asignado.')
             ->modalSubmitActionLabel('Sí, quitar')
             ->action(function ($record) {
                 $record->update(['asignado_id' => null]);
                 \Filament\Notifications\Notification::make()
                     ->title('🗑️ Comercial removido')
                     ->body('El lead ya no tiene comercial asignado.')
                     ->warning()
                     ->send();
             }),

            
        Action::make('forzar_cambio_estado')
        ->label('Forzar Estado')
        ->icon('heroicon-o-shield-check')
        ->color('danger')
        ->visible(fn () => auth()->user()->hasRole('super_admin'))
        ->form([
            Select::make('estado')
                ->label('Estado deseado')
                ->options($estadoOptions)
                ->required(),
        ])
        ->action(function (array $data, Lead $record) {
            // Convierte el string al enum
            $nuevoEstado = LeadEstadoEnum::tryFrom($data['estado']);
            if (! $nuevoEstado) {
                Notification::make()
                    ->title('❌ Estado inválido')
                    ->danger()
                    ->send();
                return;
            }
        
            // Actualiza el estado
            $record->update(['estado' => $nuevoEstado->value]);
        
            // Opcional: crea un comentario
            $record->comentarios()->create([
                'user_id'   => auth()->id(),
                'contenido' => 'Cambio de estado a: ' . $nuevoEstado->getLabel(),
            ]);
        
            // Usa la etiqueta de la enum en la notificación
            Notification::make()
                ->title('🛡️ Estado forzado')
                ->body("El estado se cambió a “{$nuevoEstado->getLabel()}”.")
                ->success()
                ->send();
        })
        ->modalHeading(fn (Lead $record) => "Forzar estado de “{$record->nombre}”")
        ->modalSubmitActionLabel('Aplicar'),

        ];
    }
}
