<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Cliente;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log; // <-- ¡AÑADIR ESTA LÍNEA!

class ClientePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        Log::info("ClientePolicy@before - User ID: {$user->id}, Roles: " . $user->roles->pluck('name')->implode(', ') . ", Ability: {$ability}");

        if ($user->hasRole('admin') || $user->hasRole('supervisor')) {
            Log::info("ClientePolicy@before - User is admin/supervisor, returning true (full access).");
            return true; // Admin/Supervisor puede hacer cualquier cosa
        }
        Log::info("ClientePolicy@before - User is NOT admin/supervisor, returning null (let specific policy methods decide).");
        return null; // Para otros roles, las políticas específicas decidirán
    }

    public function viewAny(User $user): bool
    {
        Log::info("ClientePolicy@viewAny - User ID: {$user->id}, Role: " . $user->roles->pluck('name')->implode(', ') . ", Accessing general list.");
        $result = $user->hasRole('asesor'); // Asumiendo que asesores pueden ver la lista general
        Log::info("ClientePolicy@viewAny - Result: " . ($result ? 'true' : 'false') . " for general list.");
        return $result;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        Log::info("ClientePolicy@view - User ID: {$user->id}, Role: " . $user->roles->pluck('name')->implode(', ') . ", Cliente ID: {$cliente->id}, Cliente asesor_id: {$cliente->asesor_id}");
        $result = $user->hasRole('asesor'); // Asumiendo que asesores pueden ver cualquier cliente
        Log::info("ClientePolicy@view - Result: " . ($result ? 'true' : 'false') . " for specific client view.");
        return $result;
    }

    public function create(User $user): bool
    {
        Log::info("ClientePolicy@create - User ID: {$user->id}, Role: " . $user->roles->pluck('name')->implode(', '));
        $result = false; // Por defecto, denegar la creación a los asesores
        Log::info("ClientePolicy@create - Result: " . ($result ? 'true' : 'false') . " for create.");
        return $result;
    }

    public function update(User $user, Cliente $cliente): bool
    {
        Log::info("ClientePolicy@update - User ID: {$user->id}, Role: " . $user->roles->pluck('name')->implode(', ') . ", Cliente ID: {$cliente->id}, Cliente asesor_id: {$cliente->asesor_id}");
        $result = false;
        if ($user->hasRole('asesor')) {
            $result = ($user->id === $cliente->asesor_id);
            Log::info("ClientePolicy@update - Asesor: User ID {$user->id} vs Cliente asesor_id {$cliente->asesor_id}. Match: " . ($result ? 'true' : 'false'));
        }
        Log::info("ClientePolicy@update - Result: " . ($result ? 'true' : 'false') . " for update.");
        return $result;
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        Log::info("ClientePolicy@delete - User ID: {$user->id}");
        $result = false; // Por defecto, denegar la eliminación a los asesores.
        Log::info("ClientePolicy@delete - Result: " . ($result ? 'true' : 'false') . " for delete.");
        return $result;
    }

    public function deleteAny(User $user): bool
    {
        Log::info("ClientePolicy@deleteAny - User ID: {$user->id}");
        $result = false; // Por defecto, denegar la eliminación masiva a los asesores.
        Log::info("ClientePolicy@deleteAny - Result: " . ($result ? 'true' : 'false') . " for deleteAny.");
        return $result;
    }

    // Asegúrate de que estos métodos no tengan placeholders '{{ ... }}'
    public function forceDelete(User $user, Cliente $cliente): bool { return false; }
    public function forceDeleteAny(User $user, Cliente $cliente): bool { return false; }
    public function restore(User $user, Cliente $cliente): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function replicate(User $user, Cliente $cliente): bool { return false; }
    public function reorder(User $user): bool { return false; }
}