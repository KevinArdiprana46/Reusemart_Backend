<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DetailTransaksi;
use App\Models\Keranjang;
use App\Models\Pembeli;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Pegawai;
use App\Models\Penitipan;
use App\Models\Penitip;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;



class TransaksiController extends Controller
{
    public function getTransaksiKurir()
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->id_jabatan !== 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with([
            'pembeli',
            'detailTransaksi.barang.detailPenitipan.penitipan.penitip'
        ])
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status_transaksi', 'belum selesai')
            ->orderBy('created_at', 'desc')
            ->get();



        return response()->json([
            'message' => 'Data transaksi berhasil diambil.',
            'data' => $transaksi
        ]);
    }

    public function getHistoryKurir()
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->id_jabatan !== 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with([
            'pembeli',
            'detailTransaksi.barang.detailPenitipan.penitipan.penitip'
        ])
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status_transaksi', 'selesai')
            ->orderBy('created_at', 'desc')
            ->get();



        return response()->json([
            'message' => 'Data transaksi berhasil diambil.',
            'data' => $transaksi
        ]);
    }

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

        // 🔐 Validasi hanya pegawai dengan id_jabatan = 3 yang boleh
        if (!$pegawai || $pegawai->id_jabatan !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ambil transaksi dan semua relasi yang dibutuhkan
        $transaksi = Transaksi::with('detailTransaksi.barang.detailPenitipan.penitipan.penitip')->find($id);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        if ($transaksi->status_transaksi !== 'dibayar') {
            return response()->json(['message' => 'Transaksi tidak valid untuk diverifikasi.'], 400);
        }

        $transaksi->status_transaksi = 'disiapkan';
        $transaksi->tanggal_pelunasan = now();
        $transaksi->save();

        $notifiedPenitipIds = [];
        Log::info(message: "sampai sini");

        foreach ($transaksi->detailTransaksi as $detail) {

            Log::info(message: "sampai sini2");
            $barang = $detail->barang;
            $detailPenitipanList = $barang->detailPenitipan ?? [];
            Log::info("$barang");
            foreach ($detailPenitipanList as $detailPenitipan) {
                $penitipan = $detailPenitipan->penitipan;
                $penitip = $penitipan?->penitip;

                if ($penitip) {
                    \Log::info("🔍 Penitip: ", [$penitip]);

                    if ($penitip->fcm_token && !in_array($penitip->id_penitip, $notifiedPenitipIds)) {
                        \Log::info("📲 Kirim notifikasi ke penitip ID {$penitip->id_penitip}");

                        sendFCMWithJWT(
                            $penitip->fcm_token,
                            'Barang Anda Disiapkan',
                            'Barang Anda sedang disiapkan untuk dikirim atau diambil.'
                        );

                        $notifiedPenitipIds[] = $penitip->id_penitip;
                    } else {
                        \Log::warning("⚠️ Tidak ada FCM token atau sudah dikirim.");
                    }
                } else {
                    \Log::warning("⚠️ Penitip tidak ditemukan dalam relasi.");
                }
            }
        }

        return response()->json([
            'message' => 'Transaksi berhasil diverifikasi dan notifikasi dikirim.',
        ]);
    }

    public function batalkanTransaksiPembeli($id)
    {
        $pembeli = Auth::guard('sanctum')->user();

        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $transaksi = Transaksi::with('detailtransaksi.barang')->find($id);

        if (!$transaksi || $transaksi->id_pembeli !== $pembeli->id_pembeli) {
            return response()->json(['message' => 'Transaksi tidak ditemukan atau bukan milik Anda.'], 404);
        }

        if ($transaksi->status_transaksi === 'dibatalkan pembeli') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 400);
        }

        $poin = floor($transaksi->total_pembayaran / 10000);

        DB::beginTransaction();
        try {
            $transaksi->status_transaksi = 'dibatalkan pembeli';
            $transaksi->save();

            foreach ($transaksi->detailtransaksi as $detail) {
                $barang = $detail->barang;
                if ($barang) {
                    $barang->status_barang = 'tersedia';
                    $barang->save();
                }
            }

            $pembeli->poin_sosial += $poin;
            $pembeli->save();

            DB::commit();

            return response()->json([
                'message' => 'Transaksi berhasil dibatalkan.',
                'poin_tambahan' => $poin,
                'total_poin_sosial' => $pembeli->poin_sosial,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat membatalkan transaksi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getRiwayatValid()
    {
        $pembeli = Auth::guard('sanctum')->user();

        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $transaksi = Transaksi::with(['detailtransaksi.barang'])
            ->where('id_pembeli', $pembeli->id_pembeli)
            ->where('status_transaksi', 'disiapkan')
            ->where('status_pengiriman', 'belum dijadwalkan')
            ->orderByDesc('created_at')
            ->get();

        if ($transaksi->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada riwayat pembelian.',
                'data' => [],
            ], 200);
        }

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
                'created_at' => $trx->created_at,
                'detail' => $trx->detailtransaksi->map(function ($d) {
                    return [
                        'id_barang' => $d->barang->id_barang,
                        'nama_barang' => $d->barang->nama_barang ?? '-',
                        'kategori_barang' => $d->barang->kategori_barang ?? '-',
                        'harga' => $d->barang->harga_barang ?? 0,
                        'jumlah' => $d->jumlah,
                        'rating_barang' => $d->barang->rating_barang ?? 0,
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Riwayat pembelian berhasil diambil.',
            'data' => $data,
        ]);
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
                'created_at' => $trx->created_at,
                'detail' => $trx->detailtransaksi->map(function ($d) {
                    return [
                        'id_barang' => $d->barang->id_barang,
                        'nama_barang' => $d->barang->nama_barang ?? '-',
                        'kategori_barang' => $d->barang->kategori_barang ?? '-',
                        'harga' => $d->barang->harga_barang ?? 0,
                        'jumlah' => $d->jumlah,
                        'rating_barang' => $d->barang->rating_barang ?? 0,
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
            ->where('status_transaksi', 'selesai')
            ->orderByDesc('created_at')
            ->get();

        if ($transaksi->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada riwayat penjualan.',
                'data' => [],
            ], 200);
        }

        $data = $transaksi->map(function ($trx) use ($penitip) {
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
                'detail' => $trx->detailtransaksi->map(function ($d) {
                    return [
                        'id_barang' => $d->barang->id_barang,
                        'nama_barang' => $d->barang->nama_barang ?? '-',
                        'kategori_barang' => $d->barang->kategori_barang ?? '-',
                        'harga' => $d->barang->harga_barang ?? 0,
                        'jumlah' => $d->jumlah,
                        'rating_barang' => $d->barang->rating_barang ?? 0,
                    ];
                }),
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

    public function batalkanOtomatis()
    {
        $transaksis = Transaksi::with('detailtransaksi.barang', 'pembeli')
            ->where('status_transaksi', 'belum bayar')
            ->where('created_at', '<', now()->subMinutes(1))
            ->get();

        foreach ($transaksis as $trx) {
            if ($trx->pembeli) {
                $trx->pembeli->poin_sosial += $trx->poin_digunakan ?? 0;
                $trx->pembeli->save();
            }

            foreach ($trx->detailtransaksi as $detail) {
                if ($detail->barang) {
                    $detail->barang->stock += $detail->jumlah;
                    $detail->barang->status_barang = 'tersedia';
                    $detail->barang->save();
                }
            }

            $trx->status_transaksi = 'batal';
            $trx->save();
        }

        return response()->json(['success' => true, 'jumlah_dibatalkan' => $transaksis->count()]);
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
        $potongan = min($request->poin_ditukar, $pembeli->poin_sosial ?? 0) * 100;
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
            $barang = $item->barang;
            \Log::info("Checkout Barang - ID: {$barang->id_barang}, Nama: {$barang->nama_barang}, Stok Sebelum: {$barang->stock}, Jumlah Dibeli: {$item->jumlah}");
            DetailTransaksi::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'id_barang' => $item->barang->id_barang,
                'jumlah' => $item->jumlah,
            ]);

            $barang = $item->barang;
            $barang->stock -= $item->jumlah;
            if ($barang->stock <= 0) {
                $barang->stock = 0;
                $barang->status_barang = 'terjual';
            }
            $barang->save();
            \Log::info("Barang Setelah Update - ID: {$barang->id_barang}, Stok Sekarang: {$barang->stock}, Status: {$barang->status_barang}");
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
        $transaksi->status_pengiriman = 'belum dijadwalkan';
        $transaksi->tanggal_pelunasan = now();
        $transaksi->save();

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diunggah.',
            'file' => $filename,
            'url' => url("storage/bukti_bayar/$filename"),
        ]);
    }


    public function konfirmasiAmbil($id_penitipan)
    {
        $penitipan = Penitipan::with(['detailpenitipan.barang', 'penitip'])->find($id_penitipan);

        if (!$penitipan) {
            return response()->json(['message' => 'Penitipan tidak ditemukan.'], 404);
        }

        $barangList = $penitipan->detailpenitipan->pluck('barang')->filter();

        if ($barangList->isEmpty()) {
            return response()->json(['message' => 'Barang tidak ditemukan.'], 404);
        }

        foreach ($barangList as $barang) {
            // Ubah status barang menjadi terjual
            $barang->status_barang = 'terjual';
            $barang->stock = 0;
            $barang->save();

            // Ambil transaksi terakhir yang melibatkan barang ini
            $transaksi = Transaksi::whereHas('detailTransaksi', function ($q) use ($barang) {
                $q->where('id_barang', $barang->id_barang);
            })->latest()->first();

            if ($transaksi) {
                $transaksi->status_transaksi = 'selesai';
                $transaksi->tanggal_pelunasan = now();
                $transaksi->save();

                // 🔔 Kirim notifikasi ke pembeli
                $pembeli = Pembeli::find($transaksi->id_pembeli);
                if ($pembeli && $pembeli->fcm_token) {
                    try {
                        sendFCMWithJWT(
                            $pembeli->fcm_token,
                            'Barang Telah Diambil',
                            'Barang yang Anda beli telah diambil.'
                        );
                    } catch (\Exception $e) {
                        Log::error("FCM error (pembeli): " . $e->getMessage());
                    }
                }
            }
        }

        // 🔔 Kirim notifikasi ke penitip (sekali saja untuk seluruh barang)
        $penitip = $penitipan->penitip;
        if ($penitip && $penitip->fcm_token) {
            try {
                sendFCMWithJWT(
                    $penitip->fcm_token,
                    'Barang Anda Telah Terjual dan Sampai di Pembeli',
                    'Barang Anda telah berhasil diambil oleh pembeli.'
                );
            } catch (\Exception $e) {
                Log::error("FCM error (penitip): " . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Konfirmasi pengambilan berhasil.']);
    }



 public function transaksiGudang()
{
    $transaksi = Transaksi::with([
        'pembeli',
        'detailtransaksi.barang.foto_barang',
    ])
        ->whereIn('status_transaksi', ['disiapkan', 'dikirim', 'belum selesai'])
        ->where(function ($query) {
            $query->where('jenis_pengiriman', 'like', '%kurir%')
                  ->orWhere('jenis_pengiriman', 'like', '%ambil%');
        })
        ->whereIn('status_pengiriman', ['belum dijadwalkan', 'dijadwalkan'])
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json($transaksi);
}


    // public function kirimBarang($id_transaksi)
    // {
    //     $pegawai = auth()->user();

    //     // 🔐 Hanya kurir (id_jabatan = 2)
    //     if (!$pegawai || $pegawai->id_jabatan !== 2) {
    //         return response()->json(['message' => 'Unauthorized'], 403);
    //     }

    //     // Ambil transaksi dan relasinya
    //     $transaksi = Transaksi::with(['penitip', 'pembeli'])->find($id_transaksi);
    //     if (!$transaksi) {
    //         return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
    //     }

    //     // Optional: hanya transaksi tertentu yang boleh dikirim
    //     if ($transaksi->status_transaksi !== 'belum selesai') {
    //         return response()->json([
    //             'message' => 'Transaksi belum dijadwalkan atau sudah dikirim.'
    //         ], 422);
    //     }

    //     // Update status menjadi dikirim
    //     $transaksi->status_transaksi = 'dikirim';
    //     $transaksi->tanggal_dikirim = now(); // tambahkan field ini jika belum ada
    //     $transaksi->save();

    //     // Kirim notifikasi ke penitip dan pembeli
    //     foreach ([$transaksi->penitip, $transaksi->pembeli] as $user) {
    //         if ($user && $user->fcm_token) {
    //             sendFCMWithJWT(
    //                 $user->fcm_token,
    //                 '📦 Barang Sedang Dikirim',
    //                 'Barang kamu sedang dikirim oleh kurir Reusemart.'
    //             );
    //         }
    //     }

    //     return response()->json([
    //         'message' => 'Barang berhasil ditandai sebagai sedang dikirim.',
    //         'status_transaksi' => $transaksi->status_transaksi,
    //     ]);
    // }

    public function jadwalkanPengirimanKurir(Request $request, $id_transaksi)
    {
        $request->validate([
            'id_pegawai' => 'required|exists:pegawai,id_pegawai',
        ]);

        $pegawai = auth()->user();
        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with(['pembeli', 'penitip'])->find($id_transaksi);
        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

       if (stripos($transaksi->jenis_pengiriman, 'kurir') === false) {
    return response()->json(['message' => 'Transaksi ini bukan jenis pengiriman dengan kurir Reusemart'], 422);
}


        $jadwal = Carbon::parse($transaksi->tanggal_pelunasan);
        $created = Carbon::parse($transaksi->created_at);
        if ($created->isToday() && $created->gt(Carbon::today()->setHour(16)) && $jadwal->isToday()) {
            return response()->json(['message' => 'Transaksi lewat jam 16 tidak bisa dijadwalkan di hari yang sama.'], 422);
        }

        $kurir = Pegawai::where('id_pegawai', $request->id_pegawai)->where('id_jabatan', 2)->first();
        if (!$kurir) {
            return response()->json(['message' => 'Pegawai yang dipilih bukan kurir.'], 422);
        }

        $transaksi->id_pegawai = $kurir->id_pegawai;
        $transaksi->status_transaksi = 'belum selesai';
        $transaksi->status_pengiriman = 'dijadwalkan';
        $transaksi->save();

        $tokenList = [
            $transaksi->penitip->fcm_token ?? null,
            $transaksi->pembeli->fcm_token ?? null,
            $kurir->fcm_token ?? null,
        ];

        foreach ($tokenList as $token) {
            if ($token) {
                sendFCMWithJWT(
                    $token,
                    '📦 Jadwal Pengiriman',
                    'Barang akan dikirim oleh kurir Reusemart.'
                );
            }
        }

        return response()->json([
            'message' => 'Pengiriman dengan kurir berhasil dijadwalkan dan notifikasi telah dikirim.',
            'data' => $transaksi,
        ]);
    }

    public function kirimBarang($id_transaksi)
    {
        $pegawai = auth()->user();

        // 🔐 Hanya kurir (id_jabatan = 2)
        if (!$pegawai || $pegawai->id_jabatan !== 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ambil transaksi dan relasinya
        $transaksi = Transaksi::with(['penitip', 'pembeli'])->find($id_transaksi);
        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        // Optional: hanya transaksi tertentu yang boleh dikirim
        if ($transaksi->status_transaksi !== 'belum selesai') {
            return response()->json([
                'message' => 'Transaksi belum dijadwalkan atau sudah dikirim.'
            ], 422);
        }

        // Update status menjadi dikirim
        $transaksi->status_transaksi = 'dikirim';
        $transaksi->tanggal_dikirim = now(); // tambahkan field ini jika belum ada
        $transaksi->save();

        // Kirim notifikasi ke penitip dan pembeli
        foreach ([$transaksi->penitip, $transaksi->pembeli] as $user) {
            if ($user && $user->fcm_token) {
                sendFCMWithJWT(
                    $user->fcm_token,
                    '📦 Barang Sedang Dikirim',
                    'Barang kamu sedang dikirim oleh kurir Reusemart.'
                );
            }
        }

        return response()->json([
            'message' => 'Barang berhasil ditandai sebagai sedang dikirim.',
            'status_transaksi' => $transaksi->status_transaksi,
        ]);
    }


    public function jadwalkanAmbilSendiri($id_transaksi)
    {
        $pegawai = auth()->user();
        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with(['pembeli', 'penitip'])->find($id_transaksi);
        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

if (strcasecmp($transaksi->jenis_pengiriman, 'Pengambilan Mandiri') !== 0) {
    return response()->json(['message' => 'Jenis pengiriman bukan untuk ambil sendiri'], 422);
}

        $transaksi->status_transaksi = 'belum selesai';
        $transaksi->status_pengiriman = 'belum dijadwalkan';
        $transaksi->tanggal_pengambilan = now();
        $transaksi->save();

        $tokenList = [
            $transaksi->penitip->fcm_token ?? null,
            $transaksi->pembeli->fcm_token ?? null,
        ];

        foreach ($tokenList as $token) {
            if ($token) {
                sendFCMWithJWT(
                    $token,
                    '📦 Jadwal Pengambilan',
                    'Barang bisa diambil' . $transaksi->tanggal_pengambilan->translatedFormat('l, d F Y H:i') . 'Sampai Jam 8 Malam'
                );
            }
        }

        return response()->json([
            'message' => 'Transaksi dicatat sebagai pengambilan mandiri (selesai).',
            'data' => $transaksi,
        ]);
    }

    public function diterimaOlehKurir($id)
    {
        $kurir = auth()->user();

        if (!$kurir || $kurir->id_jabatan !== 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::find($id);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        if ($transaksi->id_pegawai !== $kurir->id_pegawai) {
            return response()->json(['message' => 'Transaksi bukan milik Anda'], 403);
        }

        if ($transaksi->status_transaksi !== 'dikirim') {
            return response()->json(['message' => 'Transaksi belum dalam status dikirim'], 400);
        }

        $transaksi->status_transaksi = 'selesai';
        $transaksi->save();

        $tokenList = [
            ['token' => $transaksi->penitip->fcm_token ?? null, 'role' => 'penitip'],
            ['token' => $transaksi->pembeli->fcm_token ?? null, 'role' => 'pembeli'],
        ];


        foreach ($tokenList as $user) {
            if ($user['token']) {
                $message = '';

                switch ($user['role']) {
                    case 'penitip':
                        $message = 'Barang kamu sudah diterima pembeli';
                        break;
                    case 'pembeli':
                        $message = 'Barang sudah diterima';
                        break;
                }

                sendFCMWithJWT(
                    $user['token'],
                    '📦 Barang Diterima',
                    $message
                );
            }
        }

        return response()->json([
            'message' => 'Transaksi berhasil ditandai sebagai sampai',
            'data' => $transaksi,
        ]);
    }

    public function pengirimanDikirim(Request $request)
    {
        $kurir = auth()->user();

        // 🔐 Validasi role kurir
        if (!$kurir || $kurir->id_jabatan !== 2) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ambil transaksi yang statusnya 'dikirim' dan dikirim oleh kurir ini
        $transaksi = Transaksi::with(['pembeli', 'detailTransaksi.barang'])
            ->where('status_transaksi', 'dikirim')
            ->where('id_pegawai', $kurir->id_pegawai)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'message' => 'Berhasil ambil pengiriman selesai',
            'data' => $transaksi,
        ]);
    }

public function konfirmasiBarangDiterima($id_transaksi)
{
    $pegawai = auth()->user();

    if (!$pegawai || $pegawai->id_jabatan !== 7) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $transaksi = Transaksi::with(['pembeli', 'penitip'])->find($id_transaksi);

    if (!$transaksi) {
        return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
    }

    if ($transaksi->status_transaksi === 'selesai') {
        return response()->json(['message' => 'Transaksi sudah selesai.'], 400);
    }

    $transaksi->status_transaksi = 'selesai';
    $transaksi->save();

    Log::info("✅ Transaksi {$transaksi->id_transaksi} dikonfirmasi selesai oleh pegawai gudang.");

    // Kirim email ke pembeli
    try {
        if ($transaksi->pembeli && $transaksi->pembeli->email) {
            Mail::raw(
                "Halo {$transaksi->pembeli->nama_lengkap},\n\nBarang dengan nota {$transaksi->nomor_nota} telah berhasil diterima. Terima kasih telah berbelanja di ReuseMart.",
                function ($message) use ($transaksi) {
                    $message->to($transaksi->pembeli->email)
                            ->subject('📦 Barang Telah Diterima');
                }
            );
        }
    } catch (\Exception $e) {
        Log::error("Gagal kirim email ke pembeli: " . $e->getMessage());
    }

    // Kirim email ke penitip
    try {
        if ($transaksi->penitip && $transaksi->penitip->email) {
            Mail::raw(
                "Halo {$transaksi->penitip->nama_lengkap},\n\nBarang penitipan Anda dengan transaksi {$transaksi->nomor_nota} telah berhasil diambil oleh pembeli.",
                function ($message) use ($transaksi) {
                    $message->to($transaksi->penitip->email)
                            ->subject('📢 Barang Anda Telah Diambil Pembeli');
                }
            );
        }
    } catch (\Exception $e) {
        Log::error("Gagal kirim email ke penitip: " . $e->getMessage());
    }

    return response()->json([
        'message' => 'Transaksi berhasil dikonfirmasi selesai.',
        'data' => $transaksi,
    ]);
}


    public function cekTransaksiHangus()
    {
        $now = Carbon::now();
        $expiredTransaksi = Transaksi::with('penitip.penitipan')
            ->whereIn('status_transaksi', ['disiapkan', 'dikirim'])
            ->whereDate('tanggal_pengambilan', '<', $now->copy()->subDays(2)->toDateString())
            ->get();
        $jumlah = 0;

        foreach ($expiredTransaksi as $transaksi) {
            $transaksi->status_transaksi = 'hangus';
            $transaksi->save();

            if ($transaksi->penitip && $transaksi->penitip->penitipan) {
                foreach ($transaksi->penitip->penitipan as $penitipan) {
                    if ($penitipan->id_barang) {
                        $barang = Barang::find($penitipan->id_barang);
                        if ($barang) {
                            $barang->status_barang = 'donasi';
                            $barang->save();
                        }
                    }
                }
            }

            Log::info("🔥 Transaksi #{$transaksi->id_transaksi} hangus. Barang jadi donasi.");
            $jumlah++;
        }

        return $jumlah;
    }


    public function hitungKomisiHunterByTransaksi($id_transaksi)
    {
        $transaksi = Transaksi::with(['penitip.penitipan.barang', 'pegawai'])->find($id_transaksi);

        if (!$transaksi || !in_array($transaksi->status_transaksi, ['selesai', 'hangus'])) {
            return response()->json(['message' => 'Transaksi tidak valid untuk komisi'], 400);
        }

        if ($transaksi->komisi_dihitung) {
            return response()->json(['message' => 'Komisi sudah dihitung sebelumnya'], 409);
        }

        $pegawai = $transaksi->pegawai;
        if (!$pegawai || $pegawai->id_jabatan != 5) {
            return response()->json(['message' => 'Pegawai bukan hunter'], 422);
        }

        $totalKomisi = 0;
        $penitipanList = $transaksi->penitip->penitipan ?? [];

        foreach ($penitipanList as $penitipan) {
            $barang = $penitipan->barang ?? null;
            if ($barang && strtolower($barang->status_barang) === ['terjual', 'sold out']) {
                $komisi = 0.05 * $barang->harga_barang;
                $pegawai->komisi_hunter += $komisi;
                $totalKomisi += $komisi;
            }
        }

        $pegawai->save();
        $transaksi->komisi_dihitung = true;
        $transaksi->save();

        return response()->json([
            'message' => 'Komisi berhasil dihitung.',
            'total_komisi' => $totalKomisi,
        ]);
    }



    public function hitungKomisiReusemart()
    {
        $now = Carbon::now();
        $transaksiList = Transaksi::with(['penitip', 'penitip.penitipan'])
            ->where('status_transaksi', 'selesai')
            ->get();

        $jumlahDihitung = 0;
        $totalKomisi = 0;

        foreach ($transaksiList as $transaksi) {
            $penitipan = Penitipan::where('id_penitip', $transaksi->id_penitip)
                ->with('barang')
                ->get();

            foreach ($penitipan as $p) {
                $barang = $p->barang;

                if ($barang && strtolower($barang->status_barang) === ['terjual', 'sold out']) {
                    $tanggalMasuk = Carbon::parse($p->tanggal_masuk);
                    $daysDiff = $tanggalMasuk->diffInDays($now);

                    // 🔁 Perhitungan komisi berdasarkan status_perpanjangan
                    $komisi = 0;
                    if (strtolower($p->status_perpanjangan) === 'tidak diperpanjang') {
                        $komisi = $daysDiff <= 7
                            ? 0.15 * $barang->harga_barang
                            : 0.20 * $barang->harga_barang;
                    } else {
                        // Perpanjangan hanya boleh 1x perpanjangan
                        $komisi = 0.30 * $barang->harga_barang;
                    }

                    // 💾 Simpan ke transaksi
                    $transaksi->komisi_reusemart = $komisi;
                    $transaksi->save();

                    $totalKomisi += $komisi;
                    $jumlahDihitung++;

                    Log::info("💼 Komisi ReuseMart Rp{$komisi} dihitung dari barang ID {$barang->id_barang}");
                }
            }
        }

        return response()->json([
            'message' => 'Perhitungan komisi ReuseMart selesai.',
            'jumlah_dihitung' => $jumlahDihitung,
            'total_komisi_reusemart' => $totalKomisi
        ]);
    }


    public function hitungKomisiPenitip()
    {
        $transaksiList = Transaksi::with('penitip')->where('status_transaksi', 'selesai')->get();
        $totalKomisi = 0;
        $jumlahDiproses = 0;

        foreach ($transaksiList as $transaksi) {
            $penitip = $transaksi->penitip;
            if (!$penitip)
                continue;

            $penitipanList = Penitipan::where('id_penitip', $penitip->id_penitip)->with('barang')->get();

            foreach ($penitipanList as $penitipan) {
                $barang = $penitipan->barang;
                if (!$barang || strtolower($barang->status_barang) !== ['terjual', 'sold out'])
                    continue;

                $tanggalMasuk = Carbon::parse($penitipan->tanggal_masuk);
                $tanggalTerjual = Carbon::parse($transaksi->tanggal_pelunasan);
                $selisihHari = $tanggalMasuk->diffInDays($tanggalTerjual);

                // Hitung komisi untuk reusemart
                $komisiReusemart = 0;
                if (strtolower($penitipan->status_perpanjangan) === 'tidak diperpanjang') {
                    $komisiReusemart = $selisihHari <= 7 ? 0.15 : 0.20;
                } else {
                    $komisiReusemart = 0.25;
                }

                $nilaiKomisi = $komisiReusemart * $barang->harga_barang;

                // Hitung bonus penitip jika terjual < 7 hari
                $bonus = $selisihHari < 7 ? 0.1 * $nilaiKomisi : 0;

                $komisiPenitip = $barang->harga_barang - $nilaiKomisi + $bonus;

                // Tambahkan ke penitip
                $penitip->komisi += $komisiPenitip;
                $penitip->bonus += $bonus;
                $penitip->save();

                $totalKomisi += $komisiPenitip;
                $jumlahDiproses++;

                Log::info("✅ Komisi penitip Rp{$komisiPenitip} + bonus Rp{$bonus} diberikan ke {$penitip->nama_lengkap} dari barang ID {$barang->id_barang}");
            }
        }

        return response()->json([
            'message' => 'Penghitungan komisi penitip selesai.',
            'jumlah_diproses' => $jumlahDiproses,
            'total_komisi_penitip' => $totalKomisi
        ]);
    }


    public function tambahSaldoPenitip()
    {
        $transaksiList = Transaksi::where('status_transaksi', 'selesai')->get();
        $jumlahPenitipDiproses = 0;

        foreach ($transaksiList as $transaksi) {
            $penitip = $transaksi->penitip;
            if (!$penitip)
                continue;

            $penitipanList = Penitipan::where('id_penitip', $penitip->id_penitip)->get();

            $totalSaldo = 0;

            foreach ($penitipanList as $penitipan) {
                $barang = Barang::find($penitipan->id_barang);

                if ($barang && strtolower($barang->status_barang) === 'terjual') {
                    $tanggalMasuk = Carbon::parse($penitipan->tanggal_masuk);
                    $hari = $tanggalMasuk->diffInDays(Carbon::now());

                    $komisiReusemart = 0;
                    if (strtolower($penitipan->status_perpanjangan) === 'tidak diperpanjang') {
                        $komisiReusemart = $hari <= 7 ? 0.15 : 0.20;
                    } else {
                        $komisiReusemart = 0.25;
                    }

                    $komisi = $barang->harga_barang * $komisiReusemart;
                    $penghasilan = $barang->harga_barang - $komisi;

                    $totalSaldo += $penghasilan;
                }
            }

            if ($totalSaldo > 0) {
                $penitip->saldo += $totalSaldo;
                $penitip->save();
                $jumlahPenitipDiproses++;
            }
        }

        return response()->json([
            'message' => 'Penambahan saldo penitip selesai.',
            'jumlah_penitip_diproses' => $jumlahPenitipDiproses
        ]);
    }

    public function tambahPoinPembeli()
    {
        $transaksiList = Transaksi::with('pembeli')
            ->where('status_transaksi', 'selesai')
            ->get();

        $jumlahDiproses = 0;
        $totalPoinDiberikan = 0;

        foreach ($transaksiList as $transaksi) {
            $pembeli = $transaksi->pembeli;

            if (!$pembeli)
                continue;

            // Hitung poin: 1 poin tiap 10.000
            $poin_sosial = floor($transaksi->total_pembayaran / 10000);

            if ($poin_sosial > 0) {
                $pembeli->poin_sosial += $poin_sosial;
                $pembeli->save();

                $jumlahDiproses++;
                $totalPoinDiberikan += $poin_sosial;

                Log::info("🎁 {$poin_sosial} poin ditambahkan ke {$pembeli->nama_lengkap} (ID: {$pembeli->id_pembeli})");
            }
        }

        return response()->json([
            'message' => 'Penambahan poin pembeli selesai.',
            'jumlah_diproses' => $jumlahDiproses,
            'total_poin_diberikan' => $totalPoinDiberikan
        ]);
    }

    public function prosesFinalTransaksi($id_transaksi)
    {
        $transaksi = Transaksi::with([
            'detailTransaksi.barang',
            'penitip.penitipan',
            'pembeli',
            'pegawai'
        ])->find($id_transaksi);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $bolehDiproses = in_array($transaksi->status_transaksi, ['selesai', 'dikirim', 'disiapkan']) ||
            ($transaksi->status_transaksi === 'disiapkan' &&
                strtolower($transaksi->jenis_pengiriman) === 'kurir reusemart');

        if (!$bolehDiproses) {
            return response()->json(['message' => 'Transaksi tidak valid untuk diproses.'], 422);
        }

        // 1. Update status & stock barang dari detailTransaksi
        foreach ($transaksi->detailTransaksi ?? [] as $detail) {
            $barang = $detail->barang;
            if ($barang && strtolower($barang->status_barang) === 'tersedia') {
                $barang->status_barang = 'terjual';
                $barang->stock = 0;
                $barang->save();
                \Log::info("🛍 Barang ID {$barang->id_barang} => 'terjual', stock = 0");
            }
        }

        // 2. Komisi ReuseMart
        if (!$transaksi->komisi_reusemart || $transaksi->komisi_reusemart == 0) {
            $totalKomisi = 0;
            foreach ($transaksi->detailTransaksi ?? [] as $detail) {
                $barang = $detail->barang;
                if ($barang && in_array(strtolower($barang->status_barang), ['terjual', 'sold out'])) {
                    $penitipan = $barang->detailPenitipan->penitipan ?? null;
                    if ($penitipan) {
                        $hari = Carbon::parse($penitipan->tanggal_masuk)->diffInDays(Carbon::now());
                        $komisiPersen = strtolower($penitipan->status_perpanjangan) === 'tidak diperpanjang'
                            ? ($hari <= 7 ? 0.15 : 0.20)
                            : 0.30;
                        $nilai = $komisiPersen * $barang->harga_barang;
                        $totalKomisi += $nilai;
                        \Log::info("💼 Komisi ReuseMart Rp{$nilai} dari barang ID {$barang->id_barang}");
                    }
                }
            }
            $transaksi->komisi_reusemart = $totalKomisi;
            $transaksi->save();
        }

        // 3. Komisi Penitip
        $penitip = $transaksi->penitip;
        if ($penitip && $transaksi->tanggal_pelunasan) {
            foreach ($transaksi->detailTransaksi ?? [] as $detail) {
                $barang = $detail->barang;
                if ($barang && in_array(strtolower($barang->status_barang), ['terjual', 'sold out'])) {
                    $penitipan = $barang->detailPenitipan->penitipan ?? null;
                    if ($penitipan) {
                        $tanggalMasuk = Carbon::parse($penitipan->tanggal_masuk);
                        $tanggalTerjual = Carbon::parse($transaksi->tanggal_pelunasan);
                        $selisihHari = $tanggalMasuk->diffInDays($tanggalTerjual);

                        $komisiPersen = strtolower($penitipan->status_perpanjangan) === 'tidak diperpanjang'
                            ? ($selisihHari <= 7 ? 0.15 : 0.20)
                            : 0.30;

                        $nilaiKomisi = $komisiPersen * $barang->harga_barang;
                        $bonus = $selisihHari < 7 ? 0.1 * $nilaiKomisi : 0;
                        $komisiPenitip = $barang->harga_barang - $nilaiKomisi + $bonus;

                        $penitip->saldo += $komisiPenitip;
                        \Log::info("✅ Komisi penitip Rp{$komisiPenitip} (bonus Rp{$bonus}) ditambahkan ke {$penitip->nama_lengkap}");
                    }
                }
            }
            $penitip->save();
        }

        // 4. Tambah poin sosial pembeli
        $pembeli = $transaksi->pembeli;
        if ($pembeli && $pembeli->poin_sosial == 0) {
            $poin = floor($transaksi->total_pembayaran / 10000);
            if ($poin > 0) {
                $pembeli->poin_sosial += $poin;
                $pembeli->save();
                \Log::info("🎁 Poin +{$poin} ditambahkan ke {$pembeli->nama_lengkap}");
            }
        }

        // 5. Komisi Hunter
        $pegawai = $transaksi->pegawai;
        if ($pegawai && $pegawai->id_jabatan == 5) {
            $totalKomisiHunter = 0;
            foreach ($transaksi->detailTransaksi ?? [] as $detail) {
                $barang = $detail->barang;
                if (
                    $barang &&
                    in_array(strtolower($barang->status_barang), ['terjual', 'sold out']) &&
                    $barang->id_pegawai == $pegawai->id_pegawai
                ) {
                    $komisi = 0.05 * $barang->harga_barang;
                    $pegawai->komisi_hunter += $komisi;
                    $totalKomisiHunter += $komisi;
                    \Log::info("💸 Komisi Hunter Rp{$komisi} untuk {$pegawai->nama_lengkap} dari barang ID {$barang->id_barang}");
                }
            }
            if ($totalKomisiHunter > 0) {
                $pegawai->save();
                \Log::info("✅ Total Komisi Hunter ditambahkan: Rp{$totalKomisiHunter}");
            }
        }

        // 6. Ubah status transaksi menjadi selesai jika belum
        if ($transaksi->status_transaksi !== 'selesai') {
            $transaksi->status_transaksi = 'selesai';
            $transaksi->save();
            \Log::info("✅ Status transaksi #{$transaksi->id_transaksi} diubah menjadi 'selesai'");
        }

        return response()->json(['message' => 'Semua proses final transaksi berhasil.']);
    }

    public function ambilPenitipanDariTransaksi($id_transaksi)
    {
        $transaksi = Transaksi::find($id_transaksi);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        // Ambil penitipan berdasarkan id_penitip dari transaksi
        $penitipan = Penitipan::with('barang')
            ->where('id_penitip', $transaksi->id_penitip)
            ->first(); // atau get() jika ingin banyak

        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Data penitipan ditemukan',
            'penitipan' => $penitipan
        ]);
    }

    // public function generateNotaPDF($id_transaksi)
    // {
    //     $transaksi = Transaksi::with([
    //         'pembeli.alamat',
    //         'detailtransaksi.barang.penitipan',
    //         'pegawai'
    //     ])->findOrFail($id_transaksi);

    //     $data = $transaksi->toArray();

    //     $pdf = Pdf::loadView('nota.pdf', ['transaksi' => $data]);
    //     return $pdf->download("Nota_{$transaksi->nomor_nota}.pdf");
    // }


    public function semuaTransaksi()
    {
        $user = auth()->user();

        if (!$user || !in_array($user->id_role, [1, 6])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with([
            'pembeli.alamat',
            'detailtransaksi.barang.detailpenitipan.penitipan.pegawaiqc', // ✅ relasi QC
            'pegawai'
        ])->get();


        return response()->json($transaksi);
    }

    public function laporanKomisiBulanan(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000',
        ]);

        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $data = DB::table('detailtransaksi')
            ->join('barang', 'detailtransaksi.id_barang', '=', 'barang.id_barang')
            ->join('detailpenitipan', 'barang.id_barang', '=', 'detailpenitipan.id_barang')
            ->join('penitipan', 'detailpenitipan.id_penitipan', '=', 'penitipan.id_penitipan')
            ->join('transaksi', 'detailtransaksi.id_transaksi', '=', 'transaksi.id_transaksi')
            ->leftJoin('pegawai', 'barang.id_pegawai', '=', 'pegawai.id_pegawai')
            ->select(
                'barang.id_barang',
                'barang.nama_barang',
                'barang.harga_barang',
                'penitipan.tanggal_masuk',
                'transaksi.tanggal_pelunasan',
                DB::raw("CASE 
                        WHEN barang.id_pegawai IS NOT NULL 
                        THEN barang.harga_barang * 0.05 
                        ELSE 0 
                     END AS komisiHunter"),
                DB::raw("CASE 
                        WHEN transaksi.status_transaksi = 'selesai' 
                             AND DATEDIFF(transaksi.tanggal_pelunasan, penitipan.tanggal_masuk) < 7 
                        THEN barang.harga_barang * 0.20 * 0.10 
                        ELSE 0 
                     END AS bonus_penitip"),
                DB::raw("barang.harga_barang * 0.20 AS komisi_total"),
                DB::raw("(barang.harga_barang * 0.20 
                      - CASE WHEN barang.id_pegawai IS NOT NULL THEN barang.harga_barang * 0.05 ELSE 0 END 
                      - CASE 
                            WHEN transaksi.status_transaksi = 'selesai' 
                                 AND DATEDIFF(transaksi.tanggal_pelunasan, penitipan.tanggal_masuk) < 7 
                            THEN barang.harga_barang * 0.20 * 0.10 
                            ELSE 0 
                        END
                    ) AS komisiReusemart_bersih")
            )
            ->whereMonth('transaksi.tanggal_pelunasan', $bulan)
            ->whereYear('transaksi.tanggal_pelunasan', $tahun)
            ->where('transaksi.status_transaksi', 'selesai')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function laporanTransaksiPenitip($id_penitip, $bulan, $tahun)
    {
        $penitip = Penitip::find($id_penitip);
        $nama_penitip = $penitip ? $penitip->nama_lengkap : '-';

        $barangLaku = Barang::with([
            'detailPenitipan.penitipan.penitip',
            'detailTransaksi.transaksi'
        ])
            ->whereHas('detailPenitipan.penitipan', function ($q) use ($id_penitip) {
                $q->where('id_penitip', $id_penitip);
            })
            ->whereHas('detailTransaksi.transaksi', function ($q) use ($bulan, $tahun) {
                $q->where('status_transaksi', 'selesai')
                    ->whereMonth('tanggal_pelunasan', $bulan)
                    ->whereYear('tanggal_pelunasan', $tahun);
            })
            ->get();

        $laporan = $barangLaku->map(function ($barang) {
            $tanggalMasuk = optional($barang->detailPenitipan->penitipan)->tanggal_masuk;
            $tanggalLaku = optional($barang->detailTransaksi->transaksi)->tanggal_pelunasan;

            $hargaAsli = (int) $barang->harga_barang;
            $komisi = $hargaAsli * 0.20;
            $hargaBersih = $hargaAsli - $komisi;

            $selisihHari = $tanggalMasuk && $tanggalLaku
                ? Carbon::parse($tanggalMasuk)->diffInDays(Carbon::parse($tanggalLaku))
                : null;

            $bonus = ($selisihHari !== null && $selisihHari <= 7)
                ? round(0.10 * $komisi)
                : null;

            return [
                'kode_produk' => 'K' . str_pad($barang->id_barang, 3, '0', STR_PAD_LEFT),
                'nama_produk' => $barang->nama_barang,
                'tanggal_masuk' => $tanggalMasuk ? Carbon::parse($tanggalMasuk)->format('d/m/Y') : '-',
                'tanggal_laku' => $tanggalLaku ? Carbon::parse($tanggalLaku)->format('d/m/Y') : '-',
                'harga_bersih' => $hargaBersih,
                'bonus' => $bonus ?? '-',
                'pendapatan' => $hargaBersih + ($bonus ?? 0),
            ];
        });
    }

    public function storeRating(Request $request)
    {
        $request->validate([
            'id_transaksi' => 'required|exists:transaksi,id_transaksi',
            'rating' => 'required|numeric|min:1|max:5',
        ]);

        $transaksi = Transaksi::with('pembeli', 'detailTransaksi.barang')->find($request->id_transaksi);

        if (!$transaksi || $transaksi->status_transaksi !== 'selesai') {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        $ratings = [];
        foreach ($transaksi->detailTransaksi as $detail) {
            $ratings[] = RatingBarang::create([
                'id_barang' => $detail->id_barang,
                'id_pembeli' => $transaksi->id_pembeli,
                'rating' => $request->rating
            ]);
        }

        return response()->json(['message' => 'Rating berhasil disimpan', 'data' => $ratings], 201);
    }
}
