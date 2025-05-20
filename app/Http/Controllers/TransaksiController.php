<?php

namespace App\Http\Controllers;

use App\Models\DetailTransaksi;
use App\Models\Transaksi;
use App\Models\Penitipan;
use App\Models\Barang;
use Auth;
use Illuminate\Http\Request;

class TransaksiController extends Controller
{
    public function riwayatPembelian()
    {
        $pembeli = Auth::guard('sanctum')->user();

        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $transaksi = Transaksi::with(['detailtransaksi.barang'])
            ->where('id_pembeli', $pembeli->id_pembeli)
            ->orderByDesc('created_at')
            ->get();

        if ($transaksi->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada riwayat pembelian.',
                'data' => [],
            ], 200);
        }

        // Format data untuk response
        $data = $transaksi->map(function ($trx) {
            return [
                'id_transaksi' => $trx->id_transaksi,
                'status_transaksi' => $trx->status_transaksi,
                'jenis_pengiriman' => $trx->jenis_pengiriman,
                'biaya_pengiriman' => $trx->biaya_pengiriman,
                'tanggal_pengambilan' => $trx->tanggal_pengambilan,
                'total_pembayaran' => $trx->total_pembayaran,
                'nomor_nota' => $trx->nomor_nota,
                'bukti_pembayaran' => $trx->bukti_pembayaran,
                'tanggal_pelunasan' => $trx->tanggal_pelunasan,
                'poin_reward' => $trx->poin_reward,
                'poin_digunakan' => $trx->poin_digunakan,
                'detail' => $trx->detailtransaksi->map(function ($d) {
                    return [
                        'nama_barang' => $d->barang->nama_barang ?? '-',
                        'kategori_barang' => $d->barang->kategori_barang ?? '-',
                        'harga' => $d->barang->harga ?? 0,
                        'jumlah' => $d->jumlah,
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Riwayat pembelian berhasil diambil.',
            'data' => $data,
        ]);
    }

    public function riwayatPenjualan()
    {
        $penitip = Auth::guard('sanctum')->user();

        if (!$penitip || !isset($penitip->id_penitip)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $transaksi = Transaksi::with(['detailtransaksi.barang'])
            ->whereHas('detailtransaksi.barang', function ($query) use ($penitip) {
                $query->where('id_penitip', $penitip->id_penitip);
            })
            ->orderByDesc('created_at')
            ->get();

        if ($transaksi->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada riwayat penjualan.',
                'data' => [],
            ], 200);
        }

        // Format data untuk response
        $data = $transaksi->map(function ($trx) use ($penitip) {
            // Filter hanya barang dari penitip ini
            $detail = $trx->detailtransaksi->filter(function ($d) use ($penitip) {
                return $d->barang && $d->barang->id_penitip == $penitip->id_penitip;
            })->map(function ($d) {
                return [
                    'nama_barang' => $d->barang->nama_barang ?? '-',
                    'kategori_barang' => $d->barang->kategori_barang ?? '-',
                    'harga' => $d->barang->harga ?? 0,
                    'jumlah' => $d->jumlah,
                ];
            });

            return [
                'id_transaksi' => $trx->id_transaksi,
                'status_transaksi' => $trx->status_transaksi,
                'jenis_pengiriman' => $trx->jenis_pengiriman,
                'biaya_pengiriman' => $trx->biaya_pengiriman,
                'tanggal_pengambilan' => $trx->tanggal_pengambilan,
                'total_pembayaran' => $trx->total_pembayaran,
                'nomor_nota' => $trx->nomor_nota,
                'bukti_pembayaran' => $trx->bukti_pembayaran,
                'tanggal_pelunasan' => $trx->tanggal_pelunasan,
                'created_at' => $trx->created_at,
                'poin_reward' => $trx->poin_reward,
                'poin_digunakan' => $trx->poin_digunakan,
                'detail' => $detail,
            ];
        });

        return response()->json([
            'message' => 'Riwayat penjualan berhasil diambil.',
            'data' => $data,
        ]);
    }


    public function konfirmasiAmbil($id_penitipan)
    {
        // Cari penitipan
        $penitipan = Penitipan::find($id_penitipan);

        if (!$penitipan) {
            return response()->json(['message' => 'Penitipan tidak ditemukan.'], 404);
        }

        // Ambil barang terkait
        $barang = Barang::find($penitipan->id_barang);

        if (!$barang) {
            return response()->json(['message' => 'Barang tidak ditemukan.'], 404);
        }

        // Ubah status barang menjadi 'terjual'
        $barang->status_barang = 'terjual';
        $barang->save();

        // Ambil transaksi terakhir dari penitip ini
        $transaksi = Transaksi::where('id_penitip', $barang->id_penitip)
            ->orderByDesc('created_at')
            ->first();

        if ($transaksi) {
            $transaksi->status_transaksi = 'selesai';
            $transaksi->save();
        }

        return response()->json(['message' => 'Konfirmasi pengambilan berhasil.']);
    }

    public function transaksiGudang(Request $request)
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with(['pembeli', 'penitip.barang'])
            ->whereIn('status_transaksi', ['sedang disiapkan', 'dikirim'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transaksi);
    }

}


