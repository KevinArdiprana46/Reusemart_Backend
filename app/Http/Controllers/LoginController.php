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

            switch ($user->id_role) {
                case 5:
                    $role = 'owner';
                    break;
                case 6:
                    $role = 'admin';
                    break;
                case 1:
                default:
                    $role = 'pegawai';
                    break;
            }
        } else {
            Log::info("Login gagal: Email tidak ditemukan ($email)");
            return response()->json(['message' => 'Email tidak ditemukan.'], 404);
        }

        // Verifikasi password hanya jika BUKAN pegawai
        if (!$isPegawai && !Hash::check($password, $user->password)) {
            Log::info("Login gagal: Password salah untuk email $email");
            return response()->json(['message' => 'Password salah.'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info("User login: {$user->email} sebagai role: $role");

        return response()->json([
            'token' => $token,
            'token_type'   => 'Bearer',
            'role'         => $role,
            'user'         => $user,
        ]);
    }
}
