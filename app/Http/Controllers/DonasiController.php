<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donasi;

class DonasiController extends Controller
{
    public function index()
    {
        $donasi = Donasi::with(['barang', 'organisasi'])->get();
        return response()->json($donasi);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_barang'     => 'sometimes|string|max:255',
            'pesan_request'   => 'sometimes|string',
            'status_donasi'   => 'sometimes|nullable|string|max:255',
            'tanggal_donasi'  => 'sometimes|nullable|date',
            'id_barang'       => 'sometimes|nullable|exists:barang,id_barang',
            'id_organisasi'   => 'sometimes|nullable|exists:organisasi,id_organisasi',
        ]);

        $donasi = Donasi::create($validated);
        return response()->json([
            'message' => 'Donasi berhasil ditambahkan.',
            'data' => $donasi
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $donasi = Donasi::findOrFail($id);

        $validated = $request->validate([
            'nama_barang'     => 'sometimes|string|max:255',
            'pesan_request'   => 'sometimes|string',
            'status_donasi'   => 'sometimes|nullable|string|max:255',
            'tanggal_donasi'  => 'sometimes|nullable|date',
            'id_barang'       => 'sometimes|nullable|exists:barang,id_barang',
            'id_organisasi'   => 'sometimes|nullable|exists:organisasi,id_organisasi',
        ]);

        $donasi->update($validated);
        return response()->json([
            'message' => 'Donasi berhasil diperbarui.',
            'data' => $donasi
        ]);
    }

    public function destroy($id)
    {
        $donasi = Donasi::findOrFail($id);
        $donasi->delete();

        return response()->json(['message' => 'Donasi berhasil dihapus.']);
    }

    public function search(Request $request)
    {
        $keyword = $request->query('keyword');

        $donasi = Donasi::where('nama_barang', 'LIKE', $keyword . '%')->get();

        return response()->json($donasi);
    }

    // Menampilkan detail satu donasi
    public function show($id)
    {
        $donasi = Donasi::with(['barang', 'organisasi'])->findOrFail($id);
        return response()->json($donasi);
    }
}
