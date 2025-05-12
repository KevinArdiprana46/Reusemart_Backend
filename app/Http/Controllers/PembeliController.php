<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Http\Request;
use App\Models\Pembeli;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Storage;

class PembeliController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'required|string|max:255',
            'gender' => 'required|in:Laki-laki,Perempuan',
            'email' => 'required|email|unique:pembeli,email',
            'no_telepon' => 'required|string|max:15',
            'password' => 'required|string|min:6',
            'tanggal_lahir' => 'required|date',
            'poin_sosial' => 'nullable|integer|min:0',
            'image_user' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Simpan file gambar jika ada
        if ($request->hasFile('image_user')) {
            $file = $request->file('image_user');
            $filename = time() . '_image_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $file->move(public_path('storage/foto_pembeli'), $filename);
        } else {
            $filename = 'default.jpg';
        }

        // Buat pembeli baru
        $pembeli = Pembeli::create([
            'nama_lengkap' => $request->nama_lengkap,
            'gender' => $request->gender,
            'email' => $request->email,
            'no_telepon' => $request->no_telepon,
            'password' => Hash::make($request->password),
            'tanggal_lahir' => $request->tanggal_lahir,
            'poin_sosial' => $request->poin_sosial ?? 0,
            'image_user' => $filename,
            'id_role' => 2, // role pembeli
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil',
            'data' => $pembeli
        ], 201);
    }

    public function profile(Request $request)
    {
        $pembeli = Pembeli::find(auth()->id()); // atau sesuaikan sendiri

        if (!$pembeli) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json($pembeli);
    }



    public function update(Request $request)
    {
        $pembeli = Pembeli::find(auth()->id());

        if (!$pembeli) {
            return response()->json(['message' => 'Data pembeli tidak ditemukan.'], 404);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'no_telepon' => 'sometimes|string|max:20',
            'tanggal_lahir' => 'sometimes|date',
            'password' => 'sometimes|string|min:6',
            'image_user' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Hapus foto lama jika ada file baru
        if ($request->hasFile('image_user') && $pembeli->image_user && $pembeli->image_user !== 'default.jpg') {
            $oldPath = public_path('storage/foto_pembeli/' . $pembeli->image_user);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Upload baru
        if ($request->hasFile('image_user')) {
            $file = $request->file('image_user');
            $imageName = time() . '_image_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $file->move(public_path('storage/foto_pembeli'), $imageName);
            $pembeli->image_user = $imageName;
        }

        // Update kolom lain, kecuali image_user (karena sudah ditangani manual)
        $data = $request->except('image_user');
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $pembeli->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => $pembeli
        ]);
    }
}