<?php

namespace App\Http\Controllers;

use App\Models\KlaimMerchandise;
use Carbon\Carbon;
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

    public function klaimMerchandise(Request $request)
    {
        // Validasi input
        $request->validate([
            'id_merchandise' => 'required|exists:merchandise,id_merchandise', // Validasi id_merchandise
        ]);

        // Ambil data pembeli yang sedang login
        $pembeli = Auth::user();

        // 1. Ambil merchandise yang akan diklaim
        $merchandise = Merchandise::findOrFail($request->id_merchandise);

        // 2. Hitung poin yang dibutuhkan untuk klaim merchandise (misalnya, 1 poin = 10.000 dari harga barang)
        $poinDibutuhkan = $merchandise->harga_barang / 10000; // Asumsikan harga barang diukur dengan kelipatan 10.000

        // 3. Cek apakah pembeli memiliki poin yang cukup
        if ($pembeli->poin_sosial < $poinDibutuhkan) {
            return response()->json(['message' => 'Poin tidak cukup untuk klaim merchandise.'], 400);
        }

        // 4. Kurangi poin pembeli
        $pembeli->poin_sosial -= $poinDibutuhkan;
        $pembeli->save();

        // 5. Cek apakah stok merchandise cukup
        if ($merchandise->stock <= 0) {
            return response()->json(['message' => 'Merchandise sudah habis stoknya.'], 400);
        }

        // 6. Kurangi stok merchandise
        $merchandise->stock -= 1;
        $merchandise->save();

        // 7. Simpan klaim ke tabel klaim_merchandise
        $klaim = KlaimMerchandise::create([
            'id_merchandise' => $merchandise->id,
            'id_pembeli' => $pembeli->id,
            'tanggal_klaim' => Carbon::now(),
        ]);

        // 8. Response sukses
        return response()->json([
            'message' => 'Merchandise berhasil diklaim.',
            'sisa_stok' => $merchandise->stock,
            'sisa_poin' => $pembeli->poin_sosial,
            'klaim' => $klaim,
        ]);
    }


}
