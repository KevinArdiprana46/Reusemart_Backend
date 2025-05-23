<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penitipan;
use App\Models\Barang;
use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;

class PenitipanController extends Controller
{
    public function showBarangPenitip()
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipanList = Penitipan::with(['barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        return response()->json($penitipanList);
    }

    public function getBarangByKategori($kategori)
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipanList = Penitipan::with(['barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->whereHas('barang', function ($query) use ($kategori) {
                $query->whereRaw('LOWER(TRIM(kategori_barang)) = ?', [strtolower(trim($kategori))]);
            })
            ->get();

        if ($penitipanList->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan untuk kategori ini.',
                'kategori_yang_dicari' => $kategori
            ], 404);
        }

        return response()->json($penitipanList);
    }

    public function show($id)
    {
        $penitipan = Penitipan::with(['barang.foto_barang'])->find($id);

        if (!$penitipan) {
            return response()->json(['message' => 'Penitipan not found'], 404);
        }

        return response()->json([
            'penitipan' => $penitipan
        ]);
    }

    public function storePenitipanBarang(Request $request)
    {
        $request->validate([
            'id_penitip' => 'required|exists:penitip,id_penitip',
            'id_barang' => 'required|exists:barang,id_barang',
            'nama_qc' => 'required|string|max:255',
        ]);

        $tanggalMasuk = Carbon::now();
        $tanggalAkhir = $tanggalMasuk->copy()->addDays(30);
        $batasPengambilan = $tanggalAkhir->copy()->addDays(7);

        $penitipan = Penitipan::create([
            'id_penitip' => $request->id_penitip,
            'id_barang' => $request->id_barang,
            'nama_qc' => $request->nama_qc,
            'tanggal_masuk' => $tanggalMasuk->toDateString(),
            'tanggal_akhir' => $tanggalAkhir->toDateString(),
            'batas_pengambilan' => $batasPengambilan->toDateString(),
            'status_perpanjangan' => 'tidak diperpanjang',
        ]);

        return response()->json([
            'message' => 'Penitipan barang berhasil ditambahkan.',
            'data' => $penitipan
        ]);
    }
}
