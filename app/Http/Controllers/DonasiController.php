<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donasi;
use App\Models\Barang;
use App\Models\Penitip;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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
            'nama_barang' => 'required|string|max:255',
            'pesan_request' => 'required|string',
        ]);

        $user = Auth::user();
        if (!$user || $user->id_role != 4) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $donasi = Donasi::create([
            'nama_barang' => $validated['nama_barang'],
            'pesan_request' => $validated['pesan_request'],
            'status_donasi' => 'diminta',
            'tanggal_donasi' => null,
            'id_barang' => null,
            'id_organisasi' => $user->id_organisasi,
        ]);

        return response()->json([
            'message' => 'Request donasi berhasil ditambahkan.',
            'data' => $donasi,
        ], 201);
    }



    public function update(Request $request, $id)
    {
        $donasi = Donasi::findOrFail($id);

        $validated = $request->validate([
            'nama_barang' => 'sometimes|string|max:255',
            'pesan_request' => 'sometimes|string',
            'status_donasi' => 'sometimes|nullable|string|max:255',
            'tanggal_donasi' => 'sometimes|nullable|date',
            'id_barang' => 'sometimes|nullable|exists:barang,id_barang',
            'id_organisasi' => 'sometimes|nullable|exists:organisasi,id_organisasi',
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

        // Validasi status donasi
        if ($donasi->status_donasi !== 'diminta') {
            return response()->json([
                'message' => 'Donasi ini sudah diproses atau tidak dapat dikirim.',
            ], 400);
        }

        // Ambil barang beserta detailPenitipan
        $barang = Barang::with('detailPenitipan.penitipan.penitip')->findOrFail($request->id_barang);

        // Cek stok
        if ($barang->stock <= 0) {
            return response()->json([
                'message' => 'Stok barang habis. Tidak bisa mengirim barang ini.',
            ], 400);
        }

        // Update donasi
        $donasi->update([
            'id_barang' => $barang->id_barang,
            'tanggal_donasi' => $request->tanggal_donasi,
            'status_donasi' => 'disiapkan',
        ]);

        $barang->stock -= 1;
        $barang->save();

        $detail = $barang->detailPenitipan()
            ->with('penitipan.penitip')
            ->get()
            ->sortByDesc(fn($d) => $d->penitipan?->tanggal_masuk ?? '1970-01-01')
            ->first();

        $penitip = optional($detail?->penitipan)->penitip;

        // Tambahkan poin dan kirim notifikasi jika penitip ditemukan
        $poinTambahan = 0;
        if ($penitip) {
            $poinTambahan = floor($barang->harga_barang / 10000);
            $penitip->poin_sosial += $poinTambahan;
            $penitip->save();

            if ($penitip->fcm_token) {
                \Log::info("📲 Kirim FCM ke penitip ID {$penitip->id_penitip}");
                \Log::debug("🧪 Token FCM: {$penitip->fcm_token}");

                try {
                    sendFCMWithJWT(
                        $penitip->fcm_token,
                        'Barang Anda Telah Disumbangkan',
                        "Barang '{$barang->nama_barang}' telah disumbangkan ke penerima melalui program donasi Reusemart."
                    );
                } catch (\Exception $e) {
                    \Log::error("❌ Gagal kirim FCM ke penitip: " . $e->getMessage());
                }
            } else {
                \Log::warning("⚠️ Penitip tidak punya FCM token. ID: {$penitip->id_penitip}");
            }
        } else {
            \Log::warning("⚠️ Tidak ditemukan penitip untuk barang ID {$barang->id_barang}");
        }

        return response()->json([
            'message' => 'Donasi berhasil dikirim.',
            'data' => $donasi,
            'barang_tersisa' => $barang->stock,
            'poin_diberikan' => $poinTambahan,
        ]);
    }

    public function updateDonasi(Request $request, $id)
    {
        $request->validate([
            'tanggal_donasi' => 'required|date',
            'nama_penerima' => 'required|string|max:255',
            'status_donasi' => 'required|string|in:disiapkan,siap dikirim',
        ]);

        $donasi = Donasi::find($id);

        if (!$donasi) {
            return response()->json(['message' => 'Donasi tidak ditemukan.'], 404);
        }

        if ($donasi->status_donasi !== 'disiapkan') {
            return response()->json([
                'message' => 'Donasi hanya dapat diubah jika status adalah "disiapkan".'
            ], 403);
        }

        $donasi->tanggal_donasi = $request->tanggal_donasi;
        $donasi->nama_penerima = $request->nama_penerima;
        $donasi->status_donasi = $request->status_donasi;
        $donasi->save();

        return response()->json([
            'message' => 'Donasi berhasil diperbarui.',
            'donasi' => $donasi
        ]);
    }
}
