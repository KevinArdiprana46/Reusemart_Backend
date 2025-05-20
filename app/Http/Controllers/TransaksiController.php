<?php

namespace App\Http\Controllers;

use App\Models\DetailTransaksi;
use App\Models\Transaksi;
use App\Models\Penitipan;
use App\Models\Barang;
use App\Models\Pegawai;
use Carbon\Carbon;
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

        $data = $transaksi->map(function ($trx) use ($penitip) {
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

    public function jadwalkanPengirimanKurir(Request $request, $id_transaksi)
    {
        $request->validate([
            'id_pegawai' => 'required|exists:pegawai,id_pegawai',
        ]);

        $pegawai = auth()->user();
        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::find($id_transaksi);
        if (!$transaksi)
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);

        if (strtolower($transaksi->jenis_pengiriman) !== 'dikirim kurir reusemart') {
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
        $transaksi->status_transaksi = 'sedang disiapkan';
        $transaksi->save();

        \Log::info("📦 Transaksi {$transaksi->id_transaksi} dijadwalkan dikirim oleh kurir {$kurir->nama_lengkap}");

        return response()->json([
            'message' => 'Pengiriman dengan kurir berhasil dijadwalkan.',
            'data' => $transaksi,
        ]);
    }

    public function jadwalkanAmbilSendiri($id_transaksi)
    {
        $pegawai = auth()->user();
        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::find($id_transaksi);
        if (!$transaksi)
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);

        if (strtolower($transaksi->jenis_pengiriman) !== 'pengambilan sendiri') {
            return response()->json(['message' => 'Jenis pengiriman bukan untuk ambil sendiri'], 422);
        }

        $transaksi->status_transaksi = 'sedang disiapkan';
        $transaksi->save();

        \Log::info("🛒 Transaksi {$transaksi->id_transaksi} akan diambil langsung oleh pembeli");
        \Log::info("📢 Notif: Penitip & pembeli diberi tahu transaksi {$transaksi->id_transaksi} akan diambil langsung.");

        return response()->json([
            'message' => 'Transaksi dicatat sebagai pengambilan mandiri.',
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

        \Log::info("✅ Transaksi {$transaksi->id_transaksi} dikonfirmasi selesai oleh pegawai gudang.");
        \Log::info("📢 Notif ke pembeli: Barang Anda telah diterima.");
        \Log::info("📢 Notif ke penitip: Barang Anda telah berhasil diambil oleh pembeli.");

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

            \Log::info("🔥 Transaksi #{$transaksi->id_transaksi} hangus. Barang jadi donasi.");
            $jumlah++;
        }

        return response()->json([
            'message' => 'Cek dan update transaksi hangus selesai.',
            'jumlah_dihanguskan' => $jumlah,
        ]);
    }

    public function hitungKomisiHunter()
    {
        $transaksiList = Transaksi::with(['penitip.penitipan.barang', 'pegawai'])
            ->where('status_transaksi', 'selesai')
            ->get();

        $totalKomisi = 0;

        foreach ($transaksiList as $transaksi) {
            // Validasi hanya jika pegawai adalah hunter
            $pegawai = $transaksi->pegawai;
            if (!$pegawai || $pegawai->id_jabatan != 5) {
                continue;
            }

            // Loop semua barang dari penitipan milik penitip transaksi ini
            $penitipanList = $transaksi->penitip->penitipan ?? [];

            foreach ($penitipanList as $penitipan) {
                $barang = $penitipan->barang ?? null;

                if ($barang && strtolower($barang->status_barang) === 'terjual') {
                    $komisi = 0.05 * $barang->harga_barang;
                    $pegawai->komisi_hunter += $komisi;
                    $totalKomisi += $komisi;

                    \Log::info("💸 Komisi Rp{$komisi} untuk Hunter {$pegawai->nama_lengkap} dari barang {$barang->nama_barang}");
                }
            }

            // Simpan akumulasi ke hunter tersebut
            $pegawai->save();
        }

        return response()->json([
            'message' => 'Perhitungan komisi hunter selesai.',
            'total_komisi' => $totalKomisi,
        ]);
    }




}


