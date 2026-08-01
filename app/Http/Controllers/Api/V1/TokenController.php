<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:60'],
        ]);

        $credentials['email'] = Str::lower(trim($credentials['email']));

        // Limitamos por email + IP, pero no guardamos el correo en claro en el
        // backend de rate limiting: el identificador queda seudonimizado.
        $key = 'tokens:'.hash('sha256', $credentials['email'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Prueba en '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            // Mensaje único: no revelamos si el correo existe.
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        RateLimiter::clear($key);

        // Habilidades mínimas: este token no puede tocar el panel de admin
        // aunque el usuario sea administrador en la web.
        $token = $user->createToken($credentials['device_name'], ['orders:read', 'catalog:read']);

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => ['orders:read', 'catalog:read'],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'revoked']);
    }
}
