<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // mantém compatibilidade: segue retornando 'user' e 'token'
        $token = JWTAuth::fromUser($user);

        // Agora também devolvemos 'expires_in' (opcional no frontend)
        return response()->json([
            'user'       => $user,
            'token'      => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60, // em segundos
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciais inválidas',
                'error' => 'Credenciais inválidas',
            ], 401);
        }

        // mantém compatibilidade: resposta contém 'token'
        return response()->json([
            'token'      => $token,
            // extra opcional:
            'expires_in' => auth('api')->factory()->getTTL() * 60, // em segundos
        ]);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    // NOVO: refresh sem exigir auth:api (aceita token expirado dentro do refresh_ttl)
    public function refresh(Request $request)
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
            return response()->json([
                'token'      => $newToken,
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Sem token'], 401);
        }
    }

    // NOVO: logout invalida o token atual (se blacklist estiver habilitado)
    public function logout()
    {
        auth('api')->invalidate(true);
        return response()->json(['message' => 'Logged out']);
    }
}
