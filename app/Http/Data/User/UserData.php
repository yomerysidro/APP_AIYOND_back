<?php

namespace App\Http\Data\User;

use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class UserData
{
    public static function getUsers(): array
    {
        $users = User::orderByDesc('id_usuario')->get();

        return ['success' => true, 'message' => 'Usuarios obtenidos correctamente.', 'data' => $users];
    }

    public static function getUserById(int $id): array
    {
        $user = User::find($id);

        if (! $user) {
            return ['success' => false, 'message' => 'Usuario no encontrado.', 'data' => null];
        }

        return ['success' => true, 'message' => 'Usuario obtenido correctamente.', 'data' => $user];
    }

    public static function createUser(array $data): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $data['password'] = Hash::make($data['password']);
            $data['rol'] = $data['rol'] ?? 'user';
            $user = User::create($data);
            $connection->commit();

            return ['success' => true, 'message' => 'Usuario creado correctamente.', 'data' => $user];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function updateUser(int $id, array $data): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $user = User::lockForUpdate()->find($id);

            if (! $user) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Usuario no encontrado.', 'data' => null];
            }

            if (isset($data['password']) && $data['password'] !== '') {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->fill(array_intersect_key($data, array_flip($user->getFillable())));
            $user->save();
            $connection->commit();

            return ['success' => true, 'message' => 'Usuario actualizado correctamente.', 'data' => $user];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function deleteUser(int $id): array
    {
        $connection = DB::connection('mysql');
        $transactionStarted = false;

        try {
            $connection->beginTransaction();
            $transactionStarted = true;
            $user = User::lockForUpdate()->find($id);

            if (! $user) {
                $connection->rollBack();

                return ['success' => false, 'message' => 'Usuario no encontrado.', 'data' => null];
            }
            $user->delete();
            $connection->commit();

            return ['success' => true, 'message' => 'Usuario eliminado correctamente.', 'data' => null];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
