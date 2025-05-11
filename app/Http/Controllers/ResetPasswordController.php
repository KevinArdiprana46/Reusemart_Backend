<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        if ($user = \App\Models\Pembeli::where('email', $email)->first()) {
            $broker = 'pembelis';
        } elseif ($user = \App\Models\Organisasi::where('email', $email)->first()) {
            $broker = 'organisasis';
        // } elseif ($user = \App\Models\Penitip::where('email', $email)->first()) {
        //     $broker = 'penitips';
        } else {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        $status = Password::broker($broker)->sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Link reset berhasil dikirim ke email.'])
            : response()->json(['message' => 'Gagal mengirim link reset.'], 400);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|confirmed',
        ]);

        $email = $request->email;

        if ($user = \App\Models\Pembeli::where('email', $email)->first()) {
            $broker = 'pembelis';
        } elseif ($user = \App\Models\Organisasi::where('email', $email)->first()) {
            $broker = 'organisasis';
        // } elseif ($user = \App\Models\Penitip::where('email', $email)->first()) {
        //     $broker = 'penitips';
        } else {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        $status = Password::broker($broker)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password berhasil direset.'])
            : response()->json(['message' => 'Token tidak valid atau sudah digunakan.'], 400);
    }
}
