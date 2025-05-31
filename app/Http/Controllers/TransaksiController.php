<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DetailTransaksi;
use App\Models\Keranjang;
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
use Log;


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


    public function konfirmasiAmbil($id_penitipan)
    {
        $penitipan = Penitipan::find($id_penitipan);

        if (!$penitipan) {
            return response()->json(['message' => 'Penitipan tidak ditemukan.'], 404);
        }

        $barang = Barang::find($penitipan->id_barang);

        if (!$barang) {
            return response()->json(['message' => 'Barang tidak ditemukan.'], 404);
        }

        $barang->status_barang = 'terjual' || 'sold out';
        $barang->save();

        $transaksi = Transaksi::where('id_penitip', $barang->id_penitip)
            ->orderByDesc('created_at')
            ->first();

        if ($transaksi) {
            $transaksi->status_transaksi = 'selesai';
            $transaksi->save();
        }

        return response()->json(['message' => 'Konfirmasi pengambilan berhasil.']);
    }

    public function transaksiGudang()
    {
        $transaksi = Transaksi::with([
            'pembeli',
            'detailtransaksi.barang.foto_barang'
        ])
            ->whereIn('status_transaksi', ['sedang disiapkan', 'dikirim'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transaksi);
    }

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

        if (strcasecmp($transaksi->jenis_pengiriman, 'kurir reusemart') !== 0) {
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
        $transaksi->status_transaksi = 'dikirim';
        $transaksi->save();

        // Notifikasi email ke pembeli, penitip, dan kurir (tanpa Mail class)
        if ($transaksi->pembeli && $transaksi->pembeli->email) {
            Mail::raw("Halo {$transaksi->pembeli->nama_lengkap},\n\nBarang dengan nota {$transaksi->nomor_nota} akan segera dikirim oleh kurir.", function ($message) use ($transaksi) {
                $message->to($transaksi->pembeli->email)->subject('📦 Barang Akan Dikirim');
            });
        }
        if ($transaksi->penitip && $transaksi->penitip->email) {
            Mail::raw("Halo {$transaksi->penitip->nama_lengkap},\n\nBarang dari penitipan Anda sedang dikirim ke pembeli.", function ($message) use ($transaksi) {
                $message->to($transaksi->penitip->email)->subject('📦 Barang Penitipan Dikirim');
            });
        }
        if ($kurir && $kurir->email) {
            Mail::raw("Halo {$kurir->nama_lengkap},\n\nAnda ditugaskan untuk mengirim barang dengan nota {$transaksi->nomor_nota}.", function ($message) use ($kurir) {
                $message->to($kurir->email)->subject('🚚 Penugasan Pengiriman Barang');
            });
        }

        return response()->json([
            'message' => 'Pengiriman dengan kurir berhasil dijadwalkan dan notifikasi telah dikirim.',
            'data' => $transaksi,
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

        if (strtolower($transaksi->jenis_pengiriman) !== 'pengambilan mandiri') {
            return response()->json(['message' => 'Jenis pengiriman bukan untuk ambil sendiri'], 422);
        }

        $transaksi->status_transaksi = 'selesai';
        $transaksi->tanggal_pelunasan = now();
        $transaksi->save();

        // Notifikasi email ke pembeli dan penitip
        if ($transaksi->pembeli && $transaksi->pembeli->email) {
            Mail::raw("Halo {$transaksi->pembeli->nama_lengkap},\n\nTransaksi {$transaksi->nomor_nota} telah dijadwalkan untuk pengambilan mandiri dan dianggap selesai.", function ($message) use ($transaksi) {
                $message->to($transaksi->pembeli->email)->subject('📦 Barang Siap Diambil');
            });
        }
        if ($transaksi->penitip && $transaksi->penitip->email) {
            Mail::raw("Halo {$transaksi->penitip->nama_lengkap},\n\nBarang Anda telah dijadwalkan untuk diambil langsung oleh pembeli.", function ($message) use ($transaksi) {
                $message->to($transaksi->penitip->email)->subject('📦 Barang Diambil Mandiri');
            });
        }

        return response()->json([
            'message' => 'Transaksi dicatat sebagai pengambilan mandiri (selesai).',
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
        $transaksi->tanggal_pelunasan = now(); // jika belum diset sebelumnya
        $transaksi->save();

        // Log (opsional)
        \Log::info("✅ Transaksi {$transaksi->id_transaksi} dikonfirmasi selesai oleh pegawai gudang.");

        // Kirim email ke pembeli
        if ($transaksi->pembeli && $transaksi->pembeli->email) {
            Mail::raw(
                "Halo {$transaksi->pembeli->nama_lengkap},\n\nBarang dengan nota {$transaksi->nomor_nota} telah berhasil diterima. Terima kasih telah berbelanja di ReuseMart.",
                function ($message) use ($transaksi) {
                    $message->to($transaksi->pembeli->email)
                        ->subject('📦 Barang Telah Diterima');
                }
            );
        }

        // Kirim email ke penitip
        if ($transaksi->penitip && $transaksi->penitip->email) {
            Mail::raw(
                "Halo {$transaksi->penitip->nama_lengkap},\n\nBarang penitipan Anda dengan transaksi {$transaksi->nomor_nota} telah berhasil diambil oleh pembeli.",
                function ($message) use ($transaksi) {
                    $message->to($transaksi->penitip->email)
                        ->subject('📢 Barang Anda Telah Diambil Pembeli');
                }
            );
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
            ->whereIn('status_transaksi', ['sedang disiapkan', 'dikirim'])
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

                    \Log::info("💼 Komisi ReuseMart Rp{$komisi} dihitung dari barang ID {$barang->id_barang}");
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

                \Log::info("✅ Komisi penitip Rp{$komisiPenitip} + bonus Rp{$bonus} diberikan ke {$penitip->nama_lengkap} dari barang ID {$barang->id_barang}");
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

                \Log::info("🎁 {$poin_sosial} poin ditambahkan ke {$pembeli->nama_lengkap} (ID: {$pembeli->id_pembeli})");
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
            'penitip.penitipan.barang',
            'pembeli',
            'pegawai'
        ])->find($id_transaksi);

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        $bolehDiproses = false;

        if (in_array($transaksi->status_transaksi, ['selesai', 'dikirim'])) {
            $bolehDiproses = true;
        }

        if (
            $transaksi->status_transaksi === 'sedang disiapkan' &&
            strtolower($transaksi->jenis_pengiriman) === 'kurir reusemart'
        ) {
            $bolehDiproses = true;
        }

        if (!$bolehDiproses) {
            return response()->json(['message' => 'Transaksi tidak valid untuk diproses.'], 422);
        }

        // 1. Komisi ReuseMart
        if (!$transaksi->komisi_reusemart || $transaksi->komisi_reusemart === 0) {
            $totalKomisi = 0;
            foreach ($transaksi->penitip->penitipan ?? [] as $p) {
                foreach ($p->barang ?? [] as $barang) {
                    if (strtolower($barang->status_barang) === ['terjual', 'sold out']) {
                        $hari = Carbon::parse($p->tanggal_masuk)->diffInDays(Carbon::now());
                        $komisiPersen = strtolower($p->status_perpanjangan) === 'tidak diperpanjang'
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

        // 2. Komisi Penitip
        $penitip = $transaksi->penitip;
        if ($penitip && ($penitip->komisi == 0 && $penitip->bonus == 0)) {
            foreach ($penitip->penitipan ?? [] as $p) {
                foreach ($p->barang ?? [] as $barang) {
                    if (strtolower($barang->status_barang) === ['terjual', 'sold out']) {
                        if (!$transaksi->tanggal_pelunasan)
                            continue;

                        $tanggalMasuk = Carbon::parse($p->tanggal_masuk);
                        $tanggalTerjual = Carbon::parse($transaksi->tanggal_pelunasan);
                        $selisihHari = $tanggalMasuk->diffInDays($tanggalTerjual);

                        $komisiPersen = strtolower($p->status_perpanjangan) === 'tidak diperpanjang'
                            ? ($selisihHari <= 7 ? 0.15 : 0.20)
                            : 0.30;

                        $nilaiKomisi = $komisiPersen * $barang->harga_barang;
                        $bonus = $selisihHari < 7 ? 0.1 * $nilaiKomisi : 0;
                        $komisiPenitip = $barang->harga_barang - $nilaiKomisi + $bonus;

                        $penitip->komisi += $komisiPenitip;
                        $penitip->bonus += $bonus;

                        \Log::info("✅ Komisi penitip Rp{$komisiPenitip} + bonus Rp{$bonus} diberikan ke {$penitip->nama_lengkap}");
                    }
                }
            }
            $penitip->save();
        }

        // 3. Tambah Saldo Penitip
        if ($penitip && $penitip->saldo == 0) {
            $totalSaldo = 0;
            foreach ($penitip->penitipan ?? [] as $p) {
                foreach ($p->barang ?? [] as $barang) {
                    if (strtolower($barang->status_barang) === ['terjual', 'sold out']) {
                        $hari = Carbon::parse($p->tanggal_masuk)->diffInDays(Carbon::now());
                        $komisiPersen = strtolower($p->status_perpanjangan) === 'tidak diperpanjang'
                            ? ($hari <= 7 ? 0.15 : 0.20)
                            : 0.30;
                        $komisi = $komisiPersen * $barang->harga_barang;
                        $penghasilan = $barang->harga_barang - $komisi;
                        $totalSaldo += $penghasilan;
                    }
                }
            }
            $penitip->saldo += $totalSaldo;
            $penitip->save();
        }

        // 4. Tambah Poin Pembeli
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
        if ($pegawai && $pegawai->id_jabatan == 5 && $pegawai->komisi_hunter == 0) {
            $totalKomisiHunter = 0;
            foreach ($transaksi->penitip->penitipan ?? [] as $p) {
                foreach ($p->barang ?? [] as $barang) {
                    if (strtolower($barang->status_barang) === ['terjual', 'sold out']) {
                        $komisi = 0.05 * $barang->harga_barang;
                        $pegawai->komisi_hunter += $komisi;
                        $totalKomisiHunter += $komisi;
                        \Log::info("💸 Komisi Hunter Rp{$komisi} untuk {$pegawai->nama_lengkap} dari barang ID {$barang->id_barang}");
                    }
                }
            }
            $pegawai->save();
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
            'detailtransaksi.barang.detailpenitipan.penitipan.pegawaiQc', // ✅ relasi QC
            'pegawai'
        ])->get();


        return response()->json($transaksi);
    }



}


