<?php

namespace App\Http\Controllers;

use App\Models\DetailTransaksi;
use App\Models\Keranjang;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Auth;
use Illuminate\Http\Request;

class TransaksiController extends Controller
{

    public function getTransaksiDibayar()
    {
        try {
            $transaksi = Transaksi::with(['pembeli', 'penitip']) // opsional jika ingin menampilkan relasi
                ->where('status_transaksi', 'dibayar')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'message' => 'Daftar transaksi dengan status dibayar berhasil diambil.',
                'data' => $transaksi,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil transaksi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function tolakTransaksi($id)
    {
        $pegawai = auth()->user();

        // Cek otorisasi jika diperlukan
        // if (!$pegawai || $pegawai->id_jabatan !== 6) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $transaksi = Transaksi::find($id);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        if ($transaksi->status_transaksi !== 'dibayar') {
            return response()->json(['message' => 'Transaksi tidak valid untuk ditolak.'], 400);
        }

        $transaksi->status_transaksi = 'ditolak';
        $transaksi->save();

        return response()->json(['message' => 'Transaksi berhasil ditolak.']);
    }

    public function verifikasiTransaksi($id)
    {
        $pegawai = auth()->user();

        // if (!$pegawai || $pegawai->id_jabatan !== 6) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $transaksi = Transaksi::find($id);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        if ($transaksi->status_transaksi !== 'dibayar') {
            return response()->json(['message' => 'Transaksi tidak valid untuk diverifikasi.'], 400);
        }

        $transaksi->status_transaksi = 'disiapkan';
        $transaksi->save();


        return response()->json(['message' => 'Transaksi berhasil diverifikasi.'], 200);
    }

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
                        'harga' => $d->barang->harga_barang ?? 0,
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
                    'harga' => $d->barang->harga_barang ?? 0,
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

    public function generateNomorNota(): string
    {
        $today = Carbon::now();
        $yearMonth = $today->format('Y.m');

        $count = Transaksi::whereYear('created_at', $today->year)
            ->whereMonth('created_at', $today->month)
            ->count();

        $urut = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return $yearMonth . '.' . $urut;
    }


    public function checkout(Request $request)
    {
        $request->validate([
            'keranjang_ids' => 'required|array',
            'metode_pengiriman' => 'required|in:kurir,ambil',
            'alamat_id' => 'nullable|exists:alamat,id_alamat',
            'poin_ditukar' => 'nullable|integer|min:0',
        ]);

        $pembeli = auth()->user();

        $keranjangs = Keranjang::with('barang')
            ->whereIn('id', $request->keranjang_ids)
            ->where('id_pembeli', $pembeli->id_pembeli)
            ->get();

        if ($keranjangs->isEmpty()) {
            return response()->json(['message' => 'Keranjang tidak valid.'], 400);
        }

        // Hitung total
        $subtotal = $keranjangs->sum(fn($item) => $item->barang->harga_barang * $item->jumlah);
        $ongkir = $request->metode_pengiriman === 'kurir' && $subtotal < 1500000 ? 100000 : 0;
        $potongan = min($request->poin_ditukar, $pembeli->poin_sosial ?? 0) * 10000;
        $totalAkhir = $subtotal + $ongkir - $potongan;

        // Hitung poin
        $poinUtama = floor($subtotal / 10000);
        $bonusPoin = $subtotal >= 500000 ? floor($poinUtama * 0.2) : 0;
        $totalPoin = $poinUtama + $bonusPoin;

        // Buat transaksi
        $nota = $this->generateNomorNota();
        $transaksi = Transaksi::create([
            'id_pembeli' => $pembeli->id_pembeli,
            'status_transaksi' => 'belum bayar',
            'jenis_pengiriman' => $request->metode_pengiriman,
            'biaya_pengiriman' => $ongkir,
            'total_pembayaran' => $totalAkhir,
            'poin_reward' => $totalPoin,
            'poin_digunakan' => $request->poin_ditukar,
            'nama_pengirim' => $request->metode_pengiriman === 'kurir' ? 'Kurir Reusemart' : null,
            'nomor_nota' => $nota,
        ]);

        // Tambahkan detail transaksi
        foreach ($keranjangs as $item) {
            DetailTransaksi::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'id_barang' => $item->barang->id_barang,
                'jumlah' => $item->jumlah,
            ]);

            $barang = $item->barang;
            $barang->stock -= $item->jumlah;
            if ($barang->stock <= 0) {
                $barang->stock = 0;
                $barang->status_barang = 'sold out';
            }
            $barang->save();
        }

        // Update poin pembeli
        $pembeli->poin_sosial = ($pembeli->poin_sosial ?? 0) - $request->poin_ditukar + $totalPoin;
        $pembeli->save();

        // Hapus keranjang
        Keranjang::whereIn('id', $request->keranjang_ids)->delete();

        return response()->json([
            'message' => 'Checkout berhasil.',
            'poin_didapat' => $totalPoin,
            'poin_sekarang' => $pembeli->poin_sosial,
            'id_transaksi' => $transaksi->id_transaksi,
        ]);
    }

    public function uploadBuktiPembayaran(Request $request, $id)
    {
        $request->validate([
            'bukti_pembayaran' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $transaksi = Transaksi::find($id);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $file = $request->file('bukti_pembayaran');
        $filename = time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('storage/bukti_bayar'), $filename); // 👈 pakai public_path manual

        $transaksi->bukti_pembayaran = $filename;
        $transaksi->status_transaksi = 'dibayar';
        $transaksi->tanggal_pelunasan = now();
        $transaksi->save();

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diunggah.',
            'file' => $filename,
            'url' => url("storage/bukti_bayar/$filename"),
        ]);
    }


}
