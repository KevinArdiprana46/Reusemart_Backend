<?php

namespace App\Http\Controllers;

use App\Models\Jabatan;
use Illuminate\Http\Request;

class JabatanController extends Controller
{
    // Tampilkan semua jabatan
    public function index()
    {
        return response()->json(Jabatan::all());
    }

    // Simpan jabatan baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:255',
        ]);

        $jabatan = Jabatan::create($validated);

        return response()->json([
            'message' => 'Jabatan berhasil ditambahkan',
            'data' => $jabatan,
        ], 201);
    }

    // Tampilkan 1 jabatan
    public function show($id)
    {
        $jabatan = Jabatan::findOrFail($id);
        return response()->json($jabatan);
    }

    // Update jabatan
    public function update(Request $request, $id)
    {
        $jabatan = Jabatan::findOrFail($id);

        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:255',
        ]);

        $jabatan->update($validated);

        return response()->json([
            'message' => 'Jabatan berhasil diperbarui',
            'data' => $jabatan,
        ]);
    }

    // Hapus jabatan
    public function destroy($id)
    {
        Jabatan::destroy($id);

        return response()->json(['message' => 'Jabatan berhasil dihapus']);
    }
}
