<?php

namespace App\Http\Data\Auth;

use App\Http\Data\User\UserData;
use App\Models\User\User;
use Illuminate\Auth\AuthenticationException;

class AuthData
{
    public static function register(array $data): array
    {
        $data['rol'] = 'user';
        $response = UserData::createUser($data);

        if (! $response['success']) {
            return $response;
        }

        /** @var User $user */
        $user = $response['data'];
        $guard = auth('api');
        $token = $guard->login($user);

        return [
            'success' => true,
            'message' => 'Usuario registrado correctamente.',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ];
    }

    public static function login(array $data): array
    {
        $guard = auth('api');
        $token = $guard->attempt([
            'correo' => $data['correo'],
            'password' => $data['password'],
        ]);

        if (! $token) {
            return ['success' => false, 'message' => 'Credenciales incorrectas.', 'data' => null];
        }

        return [
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'user' => $guard->user(),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ];
    }

    public static function refresh(): array
    {
        $guard = auth('api');
        $token = $guard->refresh();

        return [
            'success' => true,
            'message' => 'Token actualizado correctamente.',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ];
    }

    public static function logout(): array
    {
        auth('api')->logout();

        return ['success' => true, 'message' => 'Sesión cerrada correctamente.', 'data' => null];
    }

    public static function me(): array
    {
        $user = auth('api')->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        return ['success' => true, 'message' => 'Usuario autenticado correctamente.', 'data' => $user];
    }
}
