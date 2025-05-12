<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;

use App\Models\Pembeli;
use App\Models\Organisasi;
use App\Models\Penitip;
use App\Models\Pegawai;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->email;
        $password = $request->password;

        // Cek di masing-masing tabel
        if ($user = Pembeli::where('email', $email)->first()) {
            $role = 'pembeli';
        } elseif ($user = Organisasi::where('email', $email)->first()) {
            $role = 'organisasi';
        } elseif ($user = Penitip::where('email', $email)->first()) {
            $role = 'penitip';
        } elseif ($user = Pegawai::where('email', $email)->first()) {
            $role = 'pegawai';
        } else {
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // Cek password
        if (!Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Password salah.'], 401);
        }

        // Buat token untuk autentikasi
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'role' => $role,
            'token' => $token,
            'user' => $user,
   ]);
}
}
