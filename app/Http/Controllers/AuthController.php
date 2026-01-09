<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
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

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Credenciais inválidas'], 401);
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
