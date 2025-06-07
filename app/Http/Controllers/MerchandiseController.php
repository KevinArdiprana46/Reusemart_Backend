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
            'poin_penukaran' => 'required|integer|min:0',
            'stock' => 'nullable|integer|min:1',
        ]);

        $pegawai = Auth::user();

        $merchandise = Merchandise::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'nama_merchandise' => $request->nama_merchandise,
            'poin_penukaran' => $request->poin_penukaran,
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

    public function listKlaim()
    {
        $user = auth()->user();
        if ($user->id_jabatan != 3) {
            return response()->json(['message' => 'Hanya CS yang dapat mengakses fitur ini.'], 403);
        }

        $klaim = KlaimMerchandise::with(['pembeli', 'merchandise'])->get();
        return response()->json($klaim);
    }


    public function klaimMerchandise(Request $request)
    {
        $request->validate([
            'id_merchandise' => 'required|exists:merchandise,id_merchandise',
        ]);

        $pembeli = Auth::user(); // diasumsikan pembeli sudah login sebagai guard default
        if ($pembeli->id_role != 2) {
            return response()->json(['message' => 'Hanya pembeli yang dapat menukarkan merchandise.'], 403);
        }

        $merchandise = Merchandise::findOrFail($request->id_merchandise);
        $poinDibutuhkan = $merchandise->poin_penukaran;

        if ($pembeli->poin_sosial < $poinDibutuhkan) {
            return response()->json(['message' => 'Poin tidak cukup untuk klaim merchandise.'], 400);
        }

        if ($merchandise->stock < 1) {
            return response()->json(['message' => 'Merchandise sudah habis stoknya.'], 400);
        }

        // Kurangi poin dan stok
        $pembeli->poin_sosial -= $poinDibutuhkan;
        $pembeli->save();

        $merchandise->stock -= 1;
        $merchandise->save();

        // Simpan klaim
        $klaim = KlaimMerchandise::create([
            'id_merchandise' => $merchandise->id_merchandise,
            'id_pembeli' => $pembeli->id_pembeli,
            'tanggal_klaim' => Carbon::now(),
            'status' => 'belum diambil',
            'tanggal_ambil' => null,
        ]);

        return response()->json([
            'message' => 'Merchandise berhasil diklaim.',
            'sisa_stok' => $merchandise->stock,
            'sisa_poin' => $pembeli->poin_sosial,
            'klaim' => $klaim,
        ]);
    }

    public function isiTanggalAmbil(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->id_jabatan != 3) {
            return response()->json(['message' => 'Hanya CS yang dapat mengakses fitur ini.'], 403);
        }

        $request->validate([
            'tanggal_ambil' => 'required|date',
        ]);

        $klaim = KlaimMerchandise::findOrFail($id);

        if ($klaim->status === 'sudah diambil') {
            return response()->json(['message' => 'Merchandise sudah diambil.'], 400);
        }

        $klaim->update([
            'tanggal_ambil' => $request->tanggal_ambil,
            'status' => 'sudah diambil',
        ]);

        return response()->json(['message' => 'Tanggal ambil berhasil disimpan.']);
    }
}
