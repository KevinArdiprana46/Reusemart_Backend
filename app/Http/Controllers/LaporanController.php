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

        // Status yang dianggap sebagai penjualan berhasil
        $statusValid = ['selesai', 'disiapkan', 'hangus'];

        // Ambil semua transaksi valid
        $transaksi = DB::table('transaksi as t')
            ->join('detailtransaksi as dt', 't.id_transaksi', '=', 'dt.id_transaksi')
            ->join('barang as b', 'dt.id_barang', '=', 'b.id_barang')
            ->join('detailpenitipan as dp', 'b.id_barang', '=', 'dp.id_barang') // pastikan ada detail penitipan
            ->whereYear('t.tanggal_pelunasan', $tahun)
            ->whereIn('t.status_transaksi', $statusValid)
            ->select('t.id_transaksi', 't.tanggal_pelunasan', 't.jenis_pengiriman', 't.biaya_pengiriman')
            ->distinct()
            ->get();

        // Kelompokkan transaksi berdasarkan bulan
        $transaksiByBulan = [];
        foreach ($transaksi as $tr) {
            $bulan = (int) date('n', strtotime($tr->tanggal_pelunasan));
            $transaksiByBulan[$bulan][] = $tr;
        }

        // Daftar nama bulan
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
            12 => 'Desember',
        ];

        $hasil = [];
        for ($i = 1; $i <= 12; $i++) {
            $bulanTransaksi = $transaksiByBulan[$i] ?? [];

            if (count($bulanTransaksi) === 0) {
                $hasil[] = [
                    'bulan' => $bulanList[$i],
                    'jumlah_terjual' => 0,
                    'total_penjualan' => 0,
                ];
                continue;
            }

            $idTransaksiBulanIni = collect($bulanTransaksi)->pluck('id_transaksi')->toArray();

            // Ambil id_barang dari detail transaksi bulan ini
            $detail = DB::table('detailtransaksi')
                ->whereIn('id_transaksi', $idTransaksiBulanIni)
                ->select('id_barang')
                ->get();

            $idBarang = $detail->pluck('id_barang')->unique()->toArray();

            // Ambil harga tiap barang
            $barang = DB::table('barang')
                ->whereIn('id_barang', $idBarang)
                ->select('harga_barang')
                ->get();

            $totalBarang = $barang->sum('harga_barang');
            $jumlahTerjual = count($idBarang);

            // Tambahkan ongkir dari transaksi yang dikirim kurir
            $totalOngkir = collect($bulanTransaksi)
                ->filter(fn($tr) => $tr->jenis_pengiriman === 'dikirim kurir reusemart')
                ->sum('biaya_pengiriman');

            $hasil[] = [
                'bulan' => $bulanList[$i],
                'jumlah_terjual' => $jumlahTerjual,
                'total_penjualan' => $totalBarang + $totalOngkir,
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
                DB::raw("COUNT(CASE WHEN t.status_transaksi IN ('dibayar', 'dikirim', 'selesai', 'disiapkan') THEN 1 END) as terjual"),
                DB::raw("COUNT(CASE WHEN t.status_transaksi NOT IN ('dibayar', 'dikirim', 'selesai', 'disiapkan') THEN 1 END) as gagal")
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

        // Sanitize query input (hilangkan spasi)
        $bulan = trim($request->query('bulan', ''));
        $tahun = trim($request->query('tahun', ''));

        $query = Penitipan::with(['detailpenitipan.barang', 'penitip'])
            ->whereDate('tanggal_akhir', '<', $today);

        // Filter bulan dan tahun jika ada
        if ($bulan !== '' && $tahun !== '') {
            $query->whereMonth('tanggal_akhir', (int) $bulan)
                ->whereYear('tanggal_akhir', (int) $tahun);
        } elseif ($tahun !== '') {
            $query->whereYear('tanggal_akhir', (int) $tahun);
        }

        // Ambil data penitipan
        $penitipanList = $query->get();

        // Proses dan format data hasil
        $result = [];
        foreach ($penitipanList as $penitipan) {
            foreach ($penitipan->detailpenitipan as $detail) {
                $barang = $detail->barang;
                if (!$barang)
                    continue;

                $result[] = [
                    'id_barang' => 'P' . $barang->id_barang,
                    'nama_produk' => $barang->nama_barang,
                    'id_penitip' => 'T' . $penitipan->penitip->id_penitip,
                    'nama_penitip' => $penitipan->penitip->nama_lengkap,
                    'tanggal_masuk' => $penitipan->tanggal_masuk,
                    'tanggal_akhir' => $penitipan->tanggal_akhir,
                    'batas_ambil' => $penitipan->batas_pengambilan,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result,
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
