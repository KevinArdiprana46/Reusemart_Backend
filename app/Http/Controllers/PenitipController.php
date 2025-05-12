<?php

namespace App\Http\Controllers;

use App\Models\Penitip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PenitipController extends Controller
{
    // Tampilkan semua penitip
    public function index()
    {
        return response()->json(Penitip::all());
    }

    // Tambah penitip baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_alamat'     => 'nullable|integer',
            'badge'         => 'required|string|max:255',
            'poin_sosial'   => 'nullable|integer',
            'nama_lengkap'  => 'required|string|max:255',
            'no_telepon'    => 'required|string|max:20',
            'email'         => 'required|email',
            'password'      => 'required|string|min:6',
            'gender'        => 'required|string',
            'tanggal_lahir' => 'required|date',
            'komisi'        => 'required|numeric',
            'bonus'         => 'required|numeric',
            'id_role'       => 'required|integer',
            'no_ktp'        => 'required|string|max:50|unique:penitip,no_ktp',
            'image_user'    => 'required|image|mimes:jpeg,png,jpg',
            'foto_ktp'      => 'required|image|mimes:jpeg,png,jpg',
        ]);
    
        // Default value untuk poin_sosial
        $validated['poin_sosial'] = $validated['poin_sosial'] ?? 0;
    
        // Upload dan hash password
        $imageUserPath = $request->file('image_user')->store('penitip', 'public');
        $fotoKtpPath   = $request->file('foto_ktp')->store('penitip', 'public');
    
        $validated['image_user'] = $imageUserPath;
        $validated['foto_ktp']   = $fotoKtpPath;
        $validated['password']   = Hash::make($validated['password']);
    
        $penitip = Penitip::create($validated);
    
        return response()->json([
            'message' => 'Penitip berhasil ditambahkan',
            'data' => $penitip,
        ], 201);
    }
    

    // Tampilkan satu penitip
    public function show($id)
    {
        $penitip = Penitip::findOrFail($id);
        return response()->json($penitip);
    }

    // Update penitip
    public function update(Request $request, $id)
    {
        $penitip = Penitip::findOrFail($id);

        $validated = $request->validate([
            'id_alamat'     => 'nullable|integer',
            'badge'         => 'sometimes|required|string|max:255',
            'poin_sosial'   => 'nullable|integer',
            'nama_lengkap'  => 'sometimes|required|string|max:255',
            'no_telepon'    => 'sometimes|required|string|max:20',
            'email'         => 'sometimes|required|email',
            'password'      => 'sometimes|required|string|min:6',
            'gender'        => 'sometimes|required|string',
            'tanggal_lahir' => 'sometimes|required|date',
            'komisi'        => 'sometimes|required|numeric',
            'bonus'         => 'sometimes|required|numeric',
            'id_role'       => 'sometimes|required|integer',
            'no_ktp'        => 'sometimes|unique:penitip,no_ktp,' . $id . ',id_penitip',
            'image_user'    => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'foto_ktp'      => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('image_user')) {
            if ($penitip->image_user && Storage::disk('public')->exists($penitip->image_user)) {
                Storage::disk('public')->delete($penitip->image_user);
            }
            $validated['image_user'] = $request->file('image_user')->store('penitip', 'public');
        }

        if ($request->hasFile('foto_ktp')) {
            if ($penitip->foto_ktp && Storage::disk('public')->exists($penitip->foto_ktp)) {
                Storage::disk('public')->delete($penitip->foto_ktp);
            }
            $validated['foto_ktp'] = $request->file('foto_ktp')->store('penitip', 'public');
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $penitip->update($validated);

        return response()->json([
            'message' => 'Penitip berhasil diperbarui',
            'data' => $penitip,
        ]);
    }

    // Hapus penitip
    public function destroy($id)
    {
        $penitip = Penitip::findOrFail($id);

        if ($penitip->image_user && Storage::disk('public')->exists($penitip->image_user)) {
            Storage::disk('public')->delete($penitip->image_user);
        }
        if ($penitip->foto_ktp && Storage::disk('public')->exists($penitip->foto_ktp)) {
            Storage::disk('public')->delete($penitip->foto_ktp);
        }

        $penitip->delete();

        return response()->json(['message' => 'Penitip berhasil dihapus']);
    }

    // Cari penitip berdasarkan nama
    public function search(Request $request)
    {
        $query = $request->query('q');
        $result = Penitip::where('nama_lengkap', 'like', "$query%")
            ->get();
        return response()->json($result);
    }
}
