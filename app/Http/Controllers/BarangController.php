<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;

class BarangController extends Controller
{
    public function index()
    {
        $barangs = Barang::all();
        return response()->json($barangs);
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
}
