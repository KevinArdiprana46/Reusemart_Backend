<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Role;
use App\Models\Pegawai;
use App\Models\Penitip;
use App\Models\Pembeli;
use App\Models\Organisasi;


class UserController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $password = $request->password;

        // Cari user berdasarkan email di berbagai tabel
        $user = null;
        $roleName = '';
        $roleId = 0;

        // Cari di tabel Pembeli
        $user = Pembeli::where('email', $email)->first();
        if ($user) {
            $roleId = $user->id_role;
            $roleName = Role::find($roleId)->nama_role; // Menentukan nama role berdasarkan id_role
        }

        // Jika tidak ditemukan di Pembeli, cek di Pegawai
        if (!$user) {
            $user = Pegawai::where('email', $email)->first();
            if ($user) {
                $roleId = $user->id_role;
                $roleName = Role::find($roleId)->nama_role;
            }
        }

        // Jika tidak ditemukan di Pegawai, cek di Penitip
        if (!$user) {
            $user = Penitip::where('email', $email)->first();
            if ($user) {
                $roleId = $user->id_role;
                $roleName = Role::find($roleId)->nama_role;
            }
        }

        // Jika tidak ditemukan di Penitip, cek di Organisasi
        if (!$user) {
            $user = Organisasi::where('email', $email)->first();
            if ($user) {
                $roleId = $user->id_role;
                $roleName = Role::find($roleId)->nama_role;
            }
        }

        // // Jika tidak ditemukan di Organisasi, cek di Owner
        // if (!$user) {
        //     $user = Owner::where('email', $email)->first();
        //     if ($user) {
        //         $roleId = $user->id_role;
        //         $roleName = Role::find($roleId)->nama_role;
        //     }
        // }

        // // Jika tidak ditemukan di Owner, cek di Admin
        // if (!$user) {
        //     $user = Admin::where('email', $email)->first();
        //     if ($user) {
        //         $roleId = $user->id_role;
        //         $roleName = Role::find($roleId)->nama_role;
        //     }
        // }

        // Jika user tidak ditemukan
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // Verifikasi password
        // if (!Hash::check($password, $user->password)) {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        // Generate token untuk autentikasi
        $token = $user->createToken('api-token')->plainTextToken;

        // Tentukan URL untuk mengarahkan user berdasarkan role
        // $redirectUrl = route(name: 'dashboard.' . strtolower($roleName)); // Arahkan ke route dashboard sesuai role

        return response()->json([
            'token' => $token,
            'role' => $roleName,
            'user' => $user->nama_lengkap,
            'email' => $user->email,
            // 'redirect_url' => $redirectUrl // URL untuk redirect ke dashboard
        ]);
    }




    public function Profile(Request $request)
    {
        $user = Auth::user(); // Ambil user yang sedang login

        // Pastikan roleName ada pada user
        if (!$user->has('role')) {
            return response()->json(['message' => 'Role tidak ditemukan'], 403);
        }

        $roleName = $user->role->nama_role; // Ambil roleName yang dikirim melalui request

        // Pencarian data pengguna berdasarkan email di tabel yang sesuai dengan role
        $userData = null;

        switch ($roleName) {
            case 'pegawai': // Pegawai
                $userData = Pegawai::where('email', $user->email)->first();
                break;

            case 'pembeli': // Pembeli
                $userData = Pembeli::where('email', $user->email)->first();
                break;

            case 'penitip': // Penitip
                $userData = Penitip::where('email', $user->email)->first();
                break;

            case 'organisasi': // Organisasi
                $userData = Organisasi::where('email', $user->email)->first();
                break;

            // case 'owner': // Owner (Jika diperlukan)
            //     $userData = Owner::where('email', $user->email)->first();
            //     break;

            // case 'admin': // Admin (Jika diperlukan)
            //     $userData = Admin::where('email', $user->email)->first();
            //     break;

            default:
                return response()->json(['message' => 'Role tidak dikenali'], 403);
        }

        // Jika data pengguna tidak ditemukan, kembalikan error
        if (!$userData) {
            return response()->json(['message' => 'Data pengguna tidak ditemukan'], 404);
        }

        // Jika role adalah pembeli, perbarui image path
        // if ($roleName === 'pembeli') {
        //     $userData->image_user = $userData->image_user
        //         ? url('http://localhost:8000/storage/foto_pembeli/' . $userData->image_user) // Sesuaikan dengan URL gambar yang sesuai
        //         : null;
        // }

        return response()->json($userData);
    }




    public function update(Request $request)
    {
        $user = Auth::user();
        $pembeli = Pembeli::where('email', $user->email)->first();

        if (!$pembeli) {
            return response()->json(['message' => 'Data pembeli tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'nama_lengkap' => 'nullable|string|max:255',
            'no_telepon' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'email' => 'nullable|email',
            'password' => 'nullable|string|min:6',
            'image_user' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Simpan dan hapus file lama jika ada
        if ($request->hasFile('image_user')) {
            if ($pembeli->image_user && Storage::disk('public')->exists($pembeli->image_user)) {
                Storage::disk('public')->delete($pembeli->image_user);
            }

            // Simpan ke folder yang benar
            $validated['image_user'] = $request->file('image_user')->store('foto_pembeli', 'public');
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $pembeli->update($validated);

        // Perbarui path untuk dikirim ke frontend
        $pembeli->image_user = $pembeli->image_user
            ? url('storage/' . $pembeli->image_user)
            : null;

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $pembeli
        ]);
    }
}
