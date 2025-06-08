<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Penitipan;
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

    public function barangPenitipanHabis(Request $request)
    {
        $today = Carbon::today();

        $bulan = $request->query('bulan');
        $tahun = $request->query('tahun');

        $query = Penitipan::with(['barang', 'penitip'])
            ->whereDate('tanggal_akhir', '<', $today);

        if ($bulan && $tahun) {
            $query->whereMonth('tanggal_akhir', $bulan)
                ->whereYear('tanggal_akhir', $tahun);
        } elseif ($tahun) {
            $query->whereYear('tanggal_akhir', $tahun);
        }

        $penitipanHabis = $query->get()->map(function ($item) {
            return [
                'id_barang' => $item->barang ? 'P' . $item->barang->id_barang : '-',
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


}