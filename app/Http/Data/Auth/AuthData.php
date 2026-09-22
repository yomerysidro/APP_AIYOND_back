<?php

namespace App\Http\Data\Auth;

use App\Http\Data\User\UserData;
use App\Models\User\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthData
{
    public static function register(array $data, Request $request): array
    {
        $data['rol'] = 'user';
        $response = UserData::createUser($data);

        if (! $response['success']) {
            return $response;
        }

        /** @var User $user */
        $user = $response['data'];
        $guard = auth('api');
        $token = $guard->claims(['jti' => (string) Str::uuid()])->login($user);
        $refreshToken = app(RefreshTokenService::class)->issue($user, $request);

        return [
            'success' => true,
            'message' => 'Usuario registrado correctamente.',
            'data' => [
                'user' => self::userPayload($user),
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ];
    }

    public static function login(array $data, Request $request): array
    {
        $guard = auth('api');
        $token = $guard->claims(['jti' => (string) Str::uuid()])->attempt([
            'correo' => $data['correo'],
            'password' => $data['password'],
        ]);

        if (! $token) {
            return ['success' => false, 'message' => 'Credenciales incorrectas.', 'data' => null];
        }

        /** @var User $user */
        $user = $guard->user();
        $refreshToken = app(RefreshTokenService::class)->issue($user, $request);

        return [
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'user' => self::userPayload($user),
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ];
    }

    public static function refresh(string $refreshToken, Request $request): array
    {
        $rotation = app(RefreshTokenService::class)->rotate($refreshToken, $request);

        if (! $rotation) {
            return ['success' => false, 'message' => 'La sesión ha expirado.', 'data' => null];
        }

        $guard = auth('api');
        $token = $guard->claims(['jti' => (string) Str::uuid()])->login($rotation['user']);

        return [
            'success' => true,
            'message' => 'Token actualizado correctamente.',
            'data' => [
                'access_token' => $token,
                'refresh_token' => $rotation['refresh_token'],
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
            ],
        ];
    }

    public static function logout(string $refreshToken): array
    {
        $guard = auth('api');
        /** @var User $user */
        $user = $guard->user();
        app(RefreshTokenService::class)->revoke($refreshToken, $user);
        $guard->logout();

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

    /** @return array{id_usuario: mixed, nombre: mixed, apellido: mixed, correo: mixed} */
    private static function userPayload(User $user): array
    {
        return $user->only(['id_usuario', 'nombre', 'apellido', 'correo']);
    }
}
