<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Penitipan;
use Carbon\Carbon;
use App\Models\Barang;

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

        $barang = $penitipan->barang;

        $idPenitip = $barang->id_penitip ?? null;

        $transaksiTerakhir = \App\Models\Transaksi::where('id_penitip', $idPenitip)
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'penitipan' => $penitipan,
            'status_transaksi' => $transaksiTerakhir->status_transaksi ?? null,
            'status_barang' => $barang->status_barang ?? null,
        ]);
    }


    public function searchBarangByNama(Request $request)
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $keyword = $request->query('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter pencarian (q) wajib diisi.'], 400);
        }

        $penitipanList = Penitipan::with(['barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->whereHas('barang', function ($query) use ($keyword) {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('nama_barang', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('kategori_barang', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('deskripsi', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('harga_barang', 'LIKE', '%' . $keyword . '%');
                });
            })
            ->get();
        if ($penitipanList->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan dengan nama tersebut.',
                'kata_kunci' => $keyword
            ], 404);
        }

        return response()->json($penitipanList);
    }

    public function perpanjangPenitipan($id)
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipan = Penitipan::where('id_penitipan', $id)
            ->where('id_penitip', $penitip->id_penitip)
            ->first();

        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan.'], 404);
        }
        if (strtolower($penitipan->status_perpanjangan) !== 'diperpanjang') {
            return response()->json([
                'message' => 'Penitipan tidak dapat diperpanjang karena status bukan "diperpanjang".',
                'status_perpanjangan' => $penitipan->status_perpanjangan
            ], 400);
        }
        $penitipan->tanggal_akhir = Carbon::parse($penitipan->tanggal_akhir)->addDays(30);
        $penitipan->batas_pengambilan = Carbon::parse($penitipan->batas_pengambilan)->addDays(30);
        $penitipan->save();
        return response()->json([
            'message' => 'Perpanjangan berhasil, masa penitipan ditambah 30 hari.',
            'penitipan' => $penitipan
        ]);
    }

    public function konfirmasiPengambilan($id)
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $penitipan = Penitipan::find($id);

        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan.'], 404);
        }
        $penitipan->status_perpanjangan = 'diambil';
        $penitipan->batas_pengambilan = Carbon::now();
        $penitipan->save();

        return response()->json([
            'message' => 'Barang berhasil dicatat sebagai diambil kembali.',
            'data' => $penitipan,
        ]);
    }

}





