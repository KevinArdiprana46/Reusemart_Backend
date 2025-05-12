<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;

class BarangController extends Controller
{
    public function index()
    {
        $barang = Barang::with('foto_barang')->get();
        return response()->json($barang);
    }

    public function show($id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }
        return response()->json($barang);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_pegawai' => 'nullable|integer',
            'nama_barang' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'kategori_barang' => 'required|string',
            'harga_barang' => 'required|numeric',
            'status_barang' => 'required|string',
            'stock' => 'required|integer',
            'tanggal_garansi' => 'nullable|date',
        ]);

        $barang = Barang::create($validated);

        return response()->json($barang, 201);
    }

    public function update(Request $request, $id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }

        $validated = $request->validate([
            'id_pegawai' => 'nullable|integer',
            'nama_barang' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'kategori_barang' => 'nullable|string',
            'harga_barang' => 'nullable|numeric',
            'status_barang' => 'nullable|string',
            'stock' => 'nullable|integer',
            'tanggal_garansi' => 'nullable|date',
        ]);

        $barang->update($validated);

        return response()->json($barang);
    }

    public function destroy($id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }

        $barang->delete();
        return response()->json(['message' => 'Barang deleted successfully']);
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        $result = Barang::where('nama_barang', 'like', "$query%")
            ->get();

        return response()->json($result);
    }

    public function getBarangDonasi()
    {
        $barang = Barang::where('status_barang', 'donasi')
            ->where('stock', '>', 0)
            ->get();

        return response()->json($barang);
    }

    public function getByKategori($kategori)
    {
        $barang = Barang::with('foto_barang')
            ->whereRaw('LOWER(TRIM(kategori_barang)) = ?', [strtolower(trim($kategori))])
            ->get();

        if ($barang->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan',
                'kategori_yang_dicari' => $kategori
            ], 404);
        }

        return response()->json($barang);
    }


    public function getAllBarangForPegawai(Request $request)
    {
        $pegawai = $request->user();

        if (!$pegawai || $pegawai->id_pegawai === null) {
            return response()->json(['message' => 'Pegawai tidak ditemukan atau belum login'], 403);
        }

        $barang = Barang::all(); // Mengambil semua data barang tanpa relasi foto_barang

        return response()->json([
            'barang' => $barang,
            'id_jabatan' => $pegawai->id_jabatan,
        ]);
    }


}
