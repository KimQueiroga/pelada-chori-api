<?php

// app/Http/Controllers/PasswordResetController.php
namespace App\Http\Controllers;

use App\Mail\ResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // 1) Enviar código
    public function sendCode(Request $req)
    {
        $data = $req->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            // Não exponha se existe ou não; responda sempre 200
            return response()->json(['message' => 'Se o e-mail existir, enviaremos um código.'], 200);
        }

        // Opcional: limite por IP/usuário
        // PasswordResetCode::where('user_id',$user->id)->active()->delete(); // invalida anteriores

        $rawCode = (string) random_int(100000, 999999);
        $record = PasswordResetCode::create([
            'user_id'   => $user->id,
            'code_hash' => Hash::make($rawCode),
            'expires_at'=> now()->addMinutes(5),
        ]);

        Mail::to($user->email)->send(new ResetCodeMail($user->name ?? 'Usuário', $rawCode));

        return response()->json([
            'message'    => 'Se o e-mail existir, enviaremos um código.',
            'expires_in' => 300
        ], 200);
    }

    // 2) Verificar código -> entrega reset_token temporário
    public function verifyCode(Request $req)
    {
        $data = $req->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) return response()->json(['error' => 'Código inválido'], 422);

        $rec = PasswordResetCode::where('user_id', $user->id)
            ->active()
            ->latest('id')
            ->first();

        if (!$rec || !Hash::check($data['code'], $rec->code_hash)) {
            return response()->json(['error' => 'Código inválido ou expirado'], 422);
        }

        $rec->reset_token = Str::random(64);
        $rec->save();

        return response()->json([
            'reset_token' => $rec->reset_token,
            'expires_in'  => $rec->expires_at->diffInSeconds(now())
        ], 200);
    }

    // 3) Reset de senha
    public function reset(Request $req)
    {
        $data = $req->validate([
            'email'       => 'required|email',
            'reset_token' => 'required|string',
            'password'    => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) return response()->json(['error' => 'Token inválido'], 422);

        $rec = PasswordResetCode::where('user_id', $user->id)
            ->active()
            ->where('reset_token', $data['reset_token'])
            ->latest('id')
            ->first();

        if (!$rec) return response()->json(['error' => 'Token inválido ou expirado'], 422);

        $user->password = Hash::make($data['password']);
        $user->save();

        $rec->used_at = now();
        $rec->save();

        // Se quiser forçar logout de JWTs antigos: (opcional — baixo risco)
        // $user->password_changed_at = now(); $user->save(); -> e validar num middleware de JWT

        return response()->json(['message' => 'Senha alterada com sucesso.'], 200);
    }
}

