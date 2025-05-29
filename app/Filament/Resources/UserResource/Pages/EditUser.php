<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;


class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
{
    $passwordUpdated = filled($data['password'] ?? null);

    // Actualiza los datos del modelo
    $record->update($data);

    if ($passwordUpdated) {
        Notification::make()
            ->title('Contraseña actualizada')
            ->body('La contraseña del usuario se ha actualizado correctamente.')
            ->success()
            ->duration(3000)
            ->send();
    }

    return $record;
}

}
