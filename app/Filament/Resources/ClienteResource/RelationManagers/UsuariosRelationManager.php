<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Models\Cliente;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;


class UsuariosRelationManager extends RelationManager
{
    protected static string $relationship = 'usuarios';
    protected static ?string $title = '👥 Usuarios con acceso al cliente';
    protected static ?string $recordTitleAttribute = 'email';

     // QUITA el “static” y asegúrate de no ponerle parámetros
     public function isReadOnly(): bool
     {
         return false;
     }

     

    public function form(Form $form): Form
    {
        return $form
        ->schema([
            \Filament\Forms\Components\Section::make()
                ->schema([
                    TextInput::make('name')
        ->label('Nombre')
        ->required(),

    TextInput::make('email')
        ->label('Email')
        ->email()
        // CORRECCIÓN: Añadir ignoreRecord: true para que funcione al editar
        ->unique(table: User::class, column: 'email', ignoreRecord: true)
        ->required(),

    TextInput::make('password')
        ->label('Contraseña (dejar vacío para no cambiar)') // Añadir pista
        ->password()
        ->revealable()
        // CORRECCIÓN: Requerido SÓLO al crear
        ->required(fn (string $operation): bool => $operation === 'create')
        // CORRECIÓN: Añadir regla de confirmación
        ->confirmed()
        // CORRECIÓN: Hashear y guardar SÓLO si se ha rellenado el campo
        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
        ->dehydrated(fn (?string $state): bool => filled($state)), // No incluir en los datos guardados si está vacío

    // NUEVO CAMPO: Confirmación de contraseña
    TextInput::make('password_confirmation')
        ->label('Confirmar Contraseña')
        ->password()
        ->revealable()
        // CORRECIÓN: Requerido SÓLO si el campo 'password' tiene algo escrito
        ->requiredWith('password'),
    
                    Toggle::make('acceso_app')
                        ->label('Acceso activado')
                        ->default(true),
                ])
                ->columns(2)
        ]);
        
       
    }
    



    public function table(Table $table): Table
    {
        return $table
           // ->recordTitleAttribute('user_id')
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('roles.name')
                ->label('Rol')
                ->badge()
                ->color('primary')
                ->getStateUsing(fn ($record) => $record->roles->pluck('name')->join(', '))
                ->sortable(),            
                TextColumn::make('created_at')
                ->label('Creado en App')
                ->dateTime('d/m/y - H:i')               
                ->sortable(),
                TextColumn::make('acceso_app')
                ->label('Acceso')
                ->formatStateUsing(fn ($state) => $state ? '✅ Activo' : '❌ Inactivo')
                ->badge()
                ->color(fn ($state) => $state ? 'success' : 'danger'),    
                
                ])
            ->filters([
                
                \Filament\Tables\Filters\Filter::make('nombre')
                    ->form([
                        Forms\Components\TextInput::make('nombre'),
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['nombre']) {
                            return $query->where('name', 'like', '%' . $data['nombre'] . '%');
                        }

                        return $query;
                    }),
                    \Filament\Tables\Filters\Filter::make('email')
                    ->form([
                        Forms\Components\TextInput::make('email'),
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['email']) {
                            return $query->where('email', 'like', '%' . $data['email'] . '%');
                        }

                        return $query;
                    }),
                  
                ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)

            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->label('➕ Añadir usuario con accesoa este cliente')
                ->icon('heroicon-o-user-plus')
                ->modalHeading('Nuevo usuario con acceso al cliente')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['password'] = bcrypt($data['password']);
                    return $data;
                })
                ->using(function (array $data): \App\Models\User {
                    $cliente = $this->getOwnerRecord(); // ✅ Obtener el cliente padre desde el relation manager
                    $user = \App\Models\User::create($data);
                    $user->assignRole('cliente_acceso');
                    $cliente->usuarios()->attach($user->id);
                    return $user;
                })
               ->after(function (\App\Models\User $record, \App\Models\Cliente $ownerRecord) {
                    Notification::make()
                        ->title('✅ Usuario  correctamente')
                        ->body("Se ha creado un usuario con NOMBRE: 👤 <span style='color:#2563eb; font-weight:bold'>{$record->name}</span> para acceder a este cliente en la plataforma AsesorFy.")
                        ->success()
                        ->send();
                }) 
                

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('toggle_acceso_app')
                ->label(fn ($record) => $record->acceso_app ? 'Quitar acceso' : 'Dar acceso')
                ->icon(fn ($record) => $record->acceso_app ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->color(fn ($record) => $record->acceso_app ? 'danger' : 'success')
                ->form([
                    Forms\Components\Toggle::make('acceso_app')
                        ->label('¿Acceso permitido?')
                        ->helperText('Activa o desactiva el acceso del usuario a la plataforma.')
                        ->default(fn ($record) => $record->acceso_app),
                ])
                ->action(function ($record, array $data) {
                  //  dd($data); // Verifica si está llegando el valor de acceso_app
                    $record->update([
                        'acceso_app' => $data['acceso_app'],
                    ]);
                })
                ->modalHeading('Configurar acceso del usuario')
                ->modalSubmitActionLabel('Actualizar')
                ->modalCancelActionLabel('Cancelar')
                ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
