<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Organisasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Penitipan;
use App\Models\Donasi;
use App\Models\Transaksi;
use Auth;

class LaporanController extends Controller
{

    public function laporanPenjualanBulanan(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->id_role != 5) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $tahun = $request->query('tahun', now()->year);

        // Ambil data dari query
        $data = DB::table('detailtransaksi as dt')
            ->join('transaksi as t', 'dt.id_transaksi', '=', 't.id_transaksi')
            ->join('barang as b', 'dt.id_barang', '=', 'b.id_barang')
            ->whereYear('t.created_at', $tahun)
            ->where('t.status_transaksi', 'selesai')
            ->select(
                DB::raw("MONTH(t.created_at) as bulan"),
                DB::raw("SUM(dt.jumlah) as jumlah_terjual"),
                DB::raw("SUM(b.harga_barang * dt.jumlah) as total_penjualan")
            )
            ->groupBy(DB::raw("MONTH(t.created_at)"))
            ->orderBy(DB::raw("MONTH(t.created_at)"))
            ->get();

        // Daftar semua bulan
        $bulanList = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        // Format hasil: jika tidak ada data, isi 0
        $hasil = [];
        for ($i = 1; $i <= 12; $i++) {
            $item = $data->firstWhere('bulan', $i);
            $hasil[] = [
                'bulan' => $bulanList[$i],
                'jumlah_terjual' => $item->jumlah_terjual ?? 0,
                'total_penjualan' => $item->total_penjualan ?? 0,
            ];
        }

        return response()->json([
            'tahun' => (int) $tahun,
            'tanggal_cetak' => now()->locale('id')->isoFormat('D MMMM Y'),
            'data' => $hasil,
        ]);
    }

    public function laporanPenjualanKategori(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->id_role != 5) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $tahun = $request->query('tahun', now()->year);

        // Ambil semua kategori unik dari tabel barang
        $semuaKategori = DB::table('barang')
            ->select('kategori_barang')
            ->distinct()
            ->pluck('kategori_barang');

        // Ambil data transaksi per kategori
        $dataTransaksi = DB::table('detailtransaksi as dt')
            ->join('transaksi as t', 'dt.id_transaksi', '=', 't.id_transaksi')
            ->join('barang as b', 'dt.id_barang', '=', 'b.id_barang')
            ->whereYear('t.created_at', $tahun)
            ->select(
                'b.kategori_barang',
                DB::raw("COUNT(CASE WHEN t.status_transaksi = 'selesai' THEN 1 END) as terjual"),
                DB::raw("COUNT(CASE WHEN t.status_transaksi != 'selesai' THEN 1 END) as gagal")
            )
            ->groupBy('b.kategori_barang')
            ->get()
            ->keyBy('kategori_barang');

        // Gabungkan dengan semua kategori
        $result = $semuaKategori->map(function ($kategori) use ($dataTransaksi) {
            return [
                'kategori' => $kategori,
                'terjual' => $dataTransaksi[$kategori]->terjual ?? 0,
                'gagal' => $dataTransaksi[$kategori]->gagal ?? 0,
            ];
        });

        return response()->json([
            'tahun' => $tahun,
            'tanggal_cetak' => now()->locale('id')->isoFormat('D MMMM Y'),
            'data' => $result->values(),
        ]);
    }

    public function barangPenitipanHabis()
    {
        $today = Carbon::today();

        $penitipanHabis = Penitipan::with(['barang', 'penitip'])
            ->whereDate('tanggal_akhir', '<', $today)
            ->get()
            ->map(function ($item) {
                return [
                    'kode_produk' => $item->barang ? 'P' . $item->barang->id_barang : '-',
                    'nama_produk' => $item->barang->nama_barang ?? '-',
                    'id_penitip' => $item->penitip ? 'T' . $item->penitip->id_penitip : '-',
                    'nama_penitip' => $item->penitip->nama_lengkap ?? '-',
                    'tanggal_masuk' => $item->tanggal_masuk,
                    'tanggal_akhir' => $item->tanggal_akhir,
                    'batas_ambil' => $item->batas_pengambilan,
                ];
            });

        return response()->json([
            'data' => $penitipanHabis,
            'tanggal_cetak' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function laporanDonasiBarang()
    {
        $data = Donasi::with([
            'barang.detailPenitipan.penitipan.penitip',
            'organisasi',
        ])->get();

        $laporan = $data->map(function ($item) {
            return [
                'kode_produk' => 'K' . str_pad($item->id_barang, 2, STR_PAD_LEFT),
                'nama_produk' => $item->barang->nama_barang ?? '-',
                'id_penitip' => 'T' . str_pad($item->barang->detailPenitipan->penitipan->penitip->id_penitip ?? null, STR_PAD_LEFT),
                'nama_penitip' => $item->barang->detailPenitipan->penitipan->penitip->nama_lengkap ?? '-',
                'tanggal_donasi' => Carbon::parse($item->tanggal_donasi)->format('d/m/Y'),
                'organisasi' => $item->organisasi->nama_organisasi ?? '-',
                'nama_penerima' => $item->organisasi->nama_penerima ?? '-',
            ];
        });

        return response()->json([
            'message' => 'Laporan donasi barang berhasil diambil.',
            'data' => $laporan,
            'tanggal_cetak' => now()->toDateString(),
        ]);
    }

    public function laporanRequestDonasi()
    {
        $data = Donasi::with('organisasi')
            ->where('status_donasi', 'diminta')
            ->get();

        return response()->json([
            'message' => 'Laporan request donasi berhasil diambil.',
            'data' => $data,
            'tanggal_cetak' => now()->toDateString(),
        ]);
    }

    public function laporanTransaksiPenitip($id_penitip, $bulan, $tahun)
    {
        $penitip = \App\Models\Penitip::find($id_penitip);
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

        return response()->json([
            'message' => 'Laporan transaksi penitip berhasil diambil.',
            'id_penitip' => $id_penitip,
            'nama_penitip' => $nama_penitip,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'data' => $laporan,
            'tanggal_cetak' => now()->toDateString(),
        ]);
    }

    public function laporanKurir(Request $request)
    {
        $idKurir = $request->query('id_kurir');
        $bulan = $request->query('bulan');
        $tahun = $request->query('tahun');

        if (!$idKurir || !$bulan || !$tahun) {
            return response()->json(['message' => 'Parameter tidak lengkap.'], 400);
        }

        $data = Transaksi::with(['pegawai', 'detailTransaksi.barang'])
            ->where('id_pegawai', $idKurir)
            ->whereMonth('tanggal_dikirim', $bulan)
            ->whereYear('tanggal_dikirim', $tahun)
            ->get()
            ->flatMap(function ($transaksi) {
                return $transaksi->detailTransaksi->map(function ($detail) use ($transaksi) {
                    return [
                        'nama_barang' => $detail->barang->nama_barang ?? '-',
                        'tanggal_kirim' => $transaksi->tanggal_dikirim
                            ? Carbon::now()->subDays(30)->toDateString()
                            : '-',
                        'nama_kurir' => $transaksi->pegawai->nama_lengkap ?? '-',
                        'status_barang' => $detail->barang->status_barang ?? '-',
                        'ongkir' => $transaksi->biaya_pengiriman ?? 0,
                    ];
                });
            });

        return response()->json([
            'data' => $data
        ]);
    }


}