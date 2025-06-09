<?php

namespace App\Http\Controllers;

use App\Models\Organisasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Penitipan;
use App\Models\Donasi;
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
}