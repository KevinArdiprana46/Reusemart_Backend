<?php

namespace App\Http\Controllers;

use App\Models\Penitip;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class PenitipController extends Controller
{
    // ✅ Register Penitip
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'badge' => 'required|string|max:255',
            'nama_lengkap' => 'required|string|max:255',
            'gender' => 'required|in:L,P',
            'email' => 'required|email|unique:penitip,email',
            'no_telepon' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'tanggal_lahir' => 'required|date',
            'poin_sosial' => 'nullable|integer|min:0',
            'komisi' => 'required|numeric',
            'bonus' => 'required|numeric',
            'no_ktp' => 'required|string|max:50|unique:penitip,no_ktp',
            'image_user' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_ktp' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle image uploads
        $imageUserName = $request->hasFile('image_user')
            ? time() . '_image_' . preg_replace('/\s+/', '_', $request->file('image_user')->getClientOriginalName())
            : 'default.jpg';

        $fotoKtpName = $request->hasFile('foto_ktp')
            ? time() . '_ktp_' . preg_replace('/\s+/', '_', $request->file('foto_ktp')->getClientOriginalName())
            : null;

        if ($request->hasFile('image_user')) {
            $request->file('image_user')->move(public_path('storage/foto_penitip'), $imageUserName);
        }

        if ($request->hasFile('foto_ktp')) {
            $request->file('foto_ktp')->move(public_path('storage/foto_ktp'), $fotoKtpName);
        }

        $penitip = Penitip::create([
            'badge' => $request->badge,
            'nama_lengkap' => $request->nama_lengkap,
            'gender' => $request->gender,
            'email' => $request->email,
            'no_telepon' => $request->no_telepon,
            'password' => Hash::make($request->password),
            'tanggal_lahir' => $request->tanggal_lahir,
            'poin_sosial' => $request->poin_sosial ?? 0,
            'komisi' => $request->komisi,
            'bonus' => $request->bonus,
            'no_ktp' => $request->no_ktp,
            'image_user' => $imageUserName,
            'foto_ktp' => $fotoKtpName,
            'id_role' => 3,
        ]);

        return response()->json([
            'message' => 'Registrasi penitip berhasil',
            'data' => $penitip
        ], 201);
    }

    // ✅ Profile Penitip
    public function profile(Request $request)
    {
        $penitip = Penitip::find(auth()->id());

        if (!$penitip) {
            return response()->json(['message' => 'Data penitip tidak ditemukan.'], 404);
        }

        return response()->json($penitip);
    }

    // ✅ Update Profil Penitip
    public function update(Request $request)
    {
        $penitip = Penitip::find(auth()->id());

        if (!$penitip) {
            return response()->json(['message' => 'Data penitip tidak ditemukan.'], 404);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'no_telepon' => 'sometimes|string|max:20',
            'tanggal_lahir' => 'sometimes|date',
            'password' => 'sometimes|string|min:6',
            'image_user' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'foto_ktp' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Hapus dan upload image_user baru
        if ($request->hasFile('image_user') && $penitip->image_user !== 'default.jpg') {
            $oldPath = public_path('storage/foto_penitip/' . $penitip->image_user);
            if (file_exists($oldPath))
                unlink($oldPath);

            $imageName = time() . '_image_' . preg_replace('/\s+/', '_', $request->file('image_user')->getClientOriginalName());
            $request->file('image_user')->move(public_path('storage/foto_penitip'), $imageName);
            $penitip->image_user = $imageName;
        }

        // Hapus dan upload foto_ktp baru
        if ($request->hasFile('foto_ktp')) {
            $oldKtp = public_path('storage/foto_ktp/' . $penitip->foto_ktp);
            if (file_exists($oldKtp))
                unlink($oldKtp);

            $ktpName = time() . '_ktp_' . preg_replace('/\s+/', '_', $request->file('foto_ktp')->getClientOriginalName());
            $request->file('foto_ktp')->move(public_path('storage/foto_ktp'), $ktpName);
            $penitip->foto_ktp = $ktpName;
        }

        $data = $request->except(['image_user', 'foto_ktp']);
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $penitip->update($data);

        return response()->json([
            'message' => 'Profil penitip berhasil diperbarui.',
            'data' => $penitip
        ]);
    }

    // ❌ Delete Penitip
    public function destroy($id)
    {
        $penitip = Penitip::find($id);

        if (!$penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan.'], 404);
        }

        if ($penitip->image_user && $penitip->image_user !== 'default.jpg') {
            $imagePath = public_path('storage/foto_penitip/' . $penitip->image_user);
            if (file_exists($imagePath))
                unlink($imagePath);
        }

        if ($penitip->foto_ktp) {
            $ktpPath = public_path('storage/foto_ktp/' . $penitip->foto_ktp);
            if (file_exists($ktpPath))
                unlink($ktpPath);
        }

        $penitip->delete();

        return response()->json(['message' => 'Penitip berhasil dihapus.']);
    }

    // 🔍 Search Penitip by nama/email/telepon
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['message' => 'Query pencarian kosong.'], 400);
        }

        $results = Penitip::where('nama_lengkap', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('no_telepon', 'like', "%{$query}%")
            ->get();

        return response()->json([
            'message' => 'Hasil pencarian penitip',
            'data' => $results
        ]);
    }

    public function showbarang(Request $request)
    {
        $penitip = auth()->user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $barang = Barang::with('foto_barang')
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        return response()->json($barang);
    }
}
