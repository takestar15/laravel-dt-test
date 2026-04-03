<?php

namespace App\Actions\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IssueAccessTokenAction
{
    /**
     * @param  array{email: string, password: string, device_name: string}  $credentials
     * @return array{token: string, expires_at: string}
     */
    public function handle(array $credentials): array
    {
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $expiresAt = now()->addDays(30);
        $token = $user->createToken($credentials['device_name'], ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
        ];
    }
}
