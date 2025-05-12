<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Pegawai;

class AdminController extends Controller
{
    public function resetPasswordPegawai(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $admin = auth()->user();
        if (!$admin || $admin->id_role != 6) {
            return response()->json(['message' => 'Hanya admin yang dapat mereset password.'], 403);
        }

        $user = Pegawai::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if (!in_array($user->id_role, [1, 5, 6])) {
            return response()->json(['message' => 'Role tidak diizinkan untuk reset.'], 403);
        }

        if (!$user->tanggal_lahir) {
            return response()->json(['message' => 'Tanggal lahir tidak tersedia.'], 400);
        }

        $user->password = $user->tanggal_lahir;
        $user->save();

        return response()->json(['message' => 'Password berhasil direset ke tanggal lahir (tanpa hash).']);
    }
}
