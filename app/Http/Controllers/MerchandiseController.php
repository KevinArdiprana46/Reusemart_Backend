<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Merchandise;
use App\Models\FotoMerchandise;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MerchandiseController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'nama_merchandise' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'stock' => 'nullable|integer|min:1',
        ]);

        $pegawai = Auth::user();
        $merchandise = Merchandise::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'nama_merchandise' => $request->nama_merchandise,
            'kategori' => $request->kategori,
            'stock' => $request->stock,
        ]);

        return response()->json([
            'message' => 'Merchandise berhasil ditambahkan.',
            'data' => $merchandise->load('pegawai')
        ]);
    }

    public function uploadFotoMerchandise(Request $request, $id)
    {
        $request->validate([
            'foto_merchandise' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $imagePath = $request->file('foto_merchandise')->store('foto_merchandise', 'public');

        $foto = FotoMerchandise::create([
            'id_merchandise' => $id,
            'foto_merchandise' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Foto merchandise berhasil ditambahkan.',
            'image_url' => asset('storage/' . $imagePath),
        ], 201);
    }

    public function index()
    {
        $merchandiseList = Merchandise::with(['pegawai', 'fotoMerchandise'])->get();

        return response()->json([
            'message' => 'Daftar merchandise berhasil diambil.',
            'data' => $merchandiseList
        ]);
    }

}
