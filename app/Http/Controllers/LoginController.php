<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Organisasi;
use App\Models\Pegawai;
use App\Models\Role;

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

        $user = null;
        $role = null;
        $isPegawai = false;

        if ($user = Pembeli::where('email', $email)->first()) {
            $role = 'pembeli';
        } elseif ($user = Organisasi::where('email', $email)->first()) {
            $role = 'organisasi';
        } elseif ($user = Penitip::where('email', $email)->first()) {
            $role = 'penitip';
        } elseif ($user = Pegawai::where('email', $email)->first()) {
            $isPegawai = true;
            if ($user->id_role == 5) {
                $role = 'owner';
            } elseif ($user->id_jabatan == 7) {
                $role = 'gudang';
            } elseif ($user->id_jabatan == 1) {
                $role = 'admin';
            } else {
                $role = 'pegawai';
            }
        } else {
            Log::info("Login gagal: Email tidak ditemukan ($email)");
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        if (
            $role === 'pegawai' &&
            in_array($user->id_role, [5, 6, 1])
        ) {
            // cek langsung plaintext (untuk admin/owner dummy)
            if ($user->password !== $password) {
                return response()->json(['message' => 'Password salah.'], 401);
            }
        } else {
            //gunakan Hash::check untuk user biasa
            if (!Hash::check($password, $user->password)) {
                return response()->json(['message' => 'Password salah.'], 401);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info("User login: {$user->email} sebagai role: $role");

        return response()->json([
            'token'       => $token,
            'token_type'  => 'Bearer',
            'role'        => $role,
            'user'        => $user,
        ])->header('X-Laravel-Debug', '✅ Login route aktif')
            ->header('X-Debug-CORS', '✅ HandleCors seharusnya aktif');
    }
}
