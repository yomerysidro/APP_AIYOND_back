<?php

namespace App\Services\Auth;

use App\Models\Auth\RefreshToken;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function issue(User $user, Request $request): string
    {
        return DB::connection('mysql')->transaction(function () use ($user, $request) {
            return $this->create($user, $request, (string) Str::uuid());
        });
    }

    /** @return array{user: User, refresh_token: string}|null */
    public function rotate(string $plainToken, Request $request): ?array
    {
        return DB::connection('mysql')->transaction(function () use ($plainToken, $request) {
            $token = RefreshToken::query()
                ->where('token_hash', $this->hash($plainToken))
                ->lockForUpdate()
                ->first();

            if (! $token || $token->revoked_at || $token->used_at || $token->expires_at->isPast()) {
                if ($token) {
                    RefreshToken::query()->where('family_id', $token->family_id)->update(['revoked_at' => now()]);
                }

                return null;
            }

            if (! hash_equals($token->device_hash, $this->deviceHash($request))) {
                RefreshToken::query()->where('family_id', $token->family_id)->update(['revoked_at' => now()]);

                return null;
            }

            $user = User::find($token->id_usuario);
            if (! $user) {
                $token->update(['revoked_at' => now()]);

                return null;
            }

            $replacementId = (string) Str::uuid();
            $replacement = $this->create($user, $request, $token->family_id, $replacementId);
            $token->update([
                'used_at' => now(),
                'revoked_at' => now(),
                'replaced_by' => $replacementId,
            ]);

            return ['user' => $user, 'refresh_token' => $replacement];
        }, 3);
    }

    public function revoke(string $plainToken, User $user): void
    {
        DB::connection('mysql')->transaction(function () use ($plainToken, $user) {
            $token = RefreshToken::query()
                ->where('token_hash', $this->hash($plainToken))
                ->where('id_usuario', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($token && ! $token->revoked_at) {
                RefreshToken::query()->where('family_id', $token->family_id)->update(['revoked_at' => now()]);
            }
        });
    }

    private function create(User $user, Request $request, string $familyId, ?string $id = null): string
    {
        $plainToken = $this->randomToken();

        RefreshToken::create([
            'id' => $id ?? (string) Str::uuid(),
            'family_id' => $familyId,
            'id_usuario' => $user->getKey(),
            'token_hash' => $this->hash($plainToken),
            'device_hash' => $this->deviceHash($request),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'expires_at' => now()->addDays(config('auth_tokens.refresh_ttl_days')),
        ]);

        return $plainToken;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function deviceHash(Request $request): string
    {
        $identifier = $request->header('X-Device-ID') ?: $request->userAgent() ?: 'unknown-device';

        return hash('sha256', $identifier);
    }
}
