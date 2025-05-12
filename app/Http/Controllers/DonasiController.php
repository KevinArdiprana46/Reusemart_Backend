<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donasi;
use App\Models\Barang;
use App\Models\Penitip;

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
            'nama_barang'     => 'required|string|max:255',
            'pesan_request'   => 'nullable|string',
            'status_donasi'   => 'required|string|max:255',
            'tanggal_donasi'  => 'required|date',
            'id_barang'       => 'nullable|exists:barang,id_barang',
            'id_organisasi'   => 'nullable|exists:organisasi,id_organisasi',
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
            'nama_barang'     => 'required|string|max:255',
            'pesan_request'   => 'nullable|string',
            'status_donasi'   => 'required|string|max:255',
            'tanggal_donasi'  => 'required|date',
            'id_barang'       => 'nullable|exists:barang,id_barang',
            'id_organisasi'   => 'nullable|exists:organisasi,id_organisasi',
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

    public function getDonasiDiminta()
    {
        $donasi = Donasi::with(['organisasi'])
            ->where('status_donasi', 'diminta')
            ->get();

        return response()->json($donasi);
    }

    public function getRiwayatDonasi()
    {
        $riwayat = Donasi::with(['organisasi', 'barang'])
            ->where('status_donasi', '!=', 'diminta')
            ->get();

        return response()->json($riwayat);
    }

    public function getDonasiDiterima()
    {
        $donasi = Donasi::with(['organisasi', 'barang'])
            ->where('status_donasi', 'diterima')
            ->orderByDesc('tanggal_donasi')
            ->get();

        return response()->json($donasi);
    }

    public function kirimDonasi(Request $request, $id)
    {
        $request->validate([
            'id_barang' => 'required|exists:barang,id_barang',
            'tanggal_donasi' => 'required|date',
        ]);

        $donasi = Donasi::findOrFail($id);

        // Pastikan status masih 'diminta'
        if ($donasi->status_donasi !== 'diminta') {
            return response()->json([
                'message' => 'Donasi ini sudah diproses atau tidak dapat dikirim.',
            ], 400);
        }

        $barang = Barang::findOrFail($request->id_barang);

        // Cek stok barang
        if ($barang->stock <= 0) {
            return response()->json([
                'message' => 'Stok barang habis. Tidak bisa mengirim barang ini.',
            ], 400);
        }

        // Update data donasi
        $donasi->update([
            'id_barang' => $barang->id_barang,
            'tanggal_donasi' => $request->tanggal_donasi,
            'status_donasi' => 'dikirim',
        ]);

        // Kurangi stok barang
        $barang->stock -= 1;
        $barang->save();

        // Tambahkan poin ke penitip
        if ($barang->id_penitip) {
            $penitip = Penitip::find($barang->id_penitip);
            if ($penitip) {
                $poinTambahan = floor($barang->harga_barang / 10000);
                $penitip->poin_sosial += $poinTambahan;
                $penitip->save();
            }
        }

        return response()->json([
            'message' => 'Donasi berhasil dikirim.',
            'data' => $donasi,
            'barang_tersisa' => $barang->stock,
            'poin_diberikan' => $poinTambahan ?? 0,
        ]);
    }
}
