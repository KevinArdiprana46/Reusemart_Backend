<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\FotoBarang;
use App\Models\Penitip;

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
    public function showNon($id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }
        return response()->json($barang);
    }

    public function getNonByKategori($kategori)
    {
        // Mengambil barang berdasarkan kategori tanpa autentikasi
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

        $barang = Barang::where('status_barang', 'tersedia')->get();

        return response()->json([
            'barang' => $barang,
            'id_jabatan' => $pegawai->id_jabatan,
        ]);
    }

    public function getAllNonBarangForPegawai()
    {
        $barang = Barang::with('foto_barang')->get(); // Mengambil semua data barang tanpa relasi foto_barang

        return response()->json([
            'barang' => $barang,

        ]);
    }

    public function getBarangTerjual()
    {
        $barang = Barang::where('status_barang', 'terjual')
            ->get();

        return response()->json($barang);
    }

    public function uploadFotoBarang(Request $request, $id)
    {
        $request->validate([
            'foto_barang' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $imagePath = $request->file('foto_barang')->store('foto_barang', 'public');

        $foto_barang = FotoBarang::create([
            'id_barang' => $id,
            'foto_barang' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Foto berhasil ditambahkan',
            'image_url' => asset('storage/' . $imagePath),
        ], 201);
    }


    public function beriRatingBarang(Request $request, $id)
    {
        $request->validate([
            'rating_barang' => 'required|integer|min:1|max:5',
        ]);

        $barang = Barang::find($id);

        if (!$barang) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        // Karena barang cuma satu, langsung ganti rating-nya
        $barang->rating_barang = $request->rating_barang;
        $barang->save();

        $this->hitungRatingPenitip($barang->id_penitip);

        return response()->json([
            'message' => 'Rating berhasil disimpan.',
            'barang' => $barang
        ]);
    }

    public function hitungRatingPenitip($id_penitip)
    {
        $rataRata = Barang::where('id_penitip', $id_penitip)
            ->where('status_barang', 'terjual') // ✅ hanya barang terjual
            ->whereNotNull('rating_barang')     // ✅ yang sudah dinilai
            ->avg('rating_barang');

        $penitip = Penitip::find($id_penitip);

        if (!$penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan'], 404);
        }

        $penitip->rating_penitip = $rataRata ? round($rataRata, 2) : 0;
        $penitip->save();

        return response()->json([
            'message' => 'Rating penitip berhasil didapatkan.',
            'rating_penitip' => $penitip->rating_penitip
        ]);
    }
}
