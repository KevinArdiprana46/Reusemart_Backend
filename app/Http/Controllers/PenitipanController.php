<?php

namespace App\Http\Controllers;

use App\Models\DetailPenitipan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Models\Penitipan;
use App\Models\Penitip;
use App\Models\Barang;

use Illuminate\Support\Facades\Auth;
use Log;

class PenitipanController extends Controller
{
    public function showAllPenitipan()
    {
        // $pegawai = auth()->user();

        // if (!$pegawai || $pegawai->id_jabatan !== 7) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $penitipan = Penitipan::with(['penitip', 'barang'])
            ->whereHas('barang') // hanya ambil penitipan yang punya barang
            ->orderBy('id_penitipan', 'desc')
            ->get();

        return response()->json($penitipan);
    }



    public function showDetailPenitipan($id)
    {
        $penitipan = Penitipan::with([
            'penitip',
            'barang.foto_barang',
            'pegawaiqc',
            'barang.pegawai'
        ])->find($id);

        if (!$penitipan) {
            return response()->json([
                'message' => 'Data penitipan tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'message' => 'Detail penitipan ditemukan.',
            'data' => $penitipan
        ]);
    }

    public function showBarangPenitip()
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipanList = Penitipan::with(['detailpenitipan.barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        return response()->json($penitipanList);
    }

    public function getBarangByKategori($kategori)
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipanList = Penitipan::with(['detailpenitipan.barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->whereHas('detailpenitipan.barang', function ($query) use ($kategori) {
                $query->whereRaw('LOWER(TRIM(kategori_barang)) = ?', [strtolower(trim($kategori))]);
            })
            ->get();

        if ($penitipanList->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan untuk kategori ini.',
                'kategori_yang_dicari' => $kategori
            ], 404);
        }

        return response()->json($penitipanList);
    }


    public function show($id)
    {
        $penitipan = Penitipan::with(['detailpenitipan.barang.foto_barang'])->find($id);

        if (!$penitipan) {
            return response()->json(['message' => 'Penitipan not found'], 404);
        }

        // Ambil barang pertama yang dititipkan
        $firstDetail = $penitipan->detailpenitipan->first();
        $firstBarang = $firstDetail->barang ?? null;

        if (!$firstBarang) {
            return response()->json(['message' => 'Barang tidak ditemukan.'], 404);
        }

        // Ambil transaksi dari tabel detailtransaksi berdasarkan id_barang
        $detailTransaksi = \App\Models\DetailTransaksi::where('id_barang', $firstBarang->id_barang)
            ->orderByDesc('id_detail')
            ->first();

        $statusTransaksi = null;

        if ($detailTransaksi && $detailTransaksi->id_transaksi) {
            $transaksi = \App\Models\Transaksi::find($detailTransaksi->id_transaksi);
            $statusTransaksi = $transaksi->status_transaksi ?? null;
        }

        return response()->json([
            'penitipan' => $penitipan,
            'barang' => $firstBarang,
            'status_transaksi' => $statusTransaksi,
            'status_barang' => $firstBarang->status_barang ?? null,
        ]);
    }




    public function searchBarangByNama(Request $request)
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $keyword = $request->query('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter pencarian (q) wajib diisi.'], 400);
        }

        $penitipanList = Penitipan::with(['detailpenitipan.barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->whereHas('detailpenitipan.barang', function ($query) use ($keyword) {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('nama_barang', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('kategori_barang', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('deskripsi', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('harga_barang', 'LIKE', '%' . $keyword . '%');
                });
            })
            ->get();

        if ($penitipanList->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan dengan nama tersebut.',
                'kata_kunci' => $keyword
            ], 404);
        }

        return response()->json($penitipanList);
    }


    public function perpanjangPenitipan($id)
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipan = Penitipan::where('id_penitipan', $id)
            ->where('id_penitip', $penitip->id_penitip)
            ->first();

        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan.'], 404);
        }
        if (strtolower($penitipan->status_perpanjangan) !== 'diperpanjang') {
            return response()->json([
                'message' => 'Penitipan tidak dapat diperpanjang karena status bukan "diperpanjang".',
                'status_perpanjangan' => $penitipan->status_perpanjangan
            ], 400);
        }
        $penitipan->tanggal_akhir = Carbon::parse($penitipan->tanggal_akhir)->addDays(30);
        $penitipan->batas_pengambilan = Carbon::parse($penitipan->batas_pengambilan)->addDays(30);
        $penitipan->save();
        return response()->json([
            'message' => 'Perpanjangan berhasil, masa penitipan ditambah 30 hari.',
            'penitipan' => $penitipan
        ]);
    }

    public function konfirmasiPengambilan($id)
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $penitipan = Penitipan::with('detailpenitipan')->find($id);

        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan.'], 404);
        }

        $idBarangList = $penitipan->detailpenitipan->pluck('id_barang');

        // Ambil transaksi terkait barang (satu transaksi saja yang terbaru)
        $transaksi = Transaksi::whereHas('detailTransaksi', function ($query) use ($idBarangList) {
            $query->whereIn('id_barang', $idBarangList);
        })->latest()->first();

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi untuk barang ini tidak ditemukan.'], 404);
        }

        if ($transaksi->status_transaksi === 'selesai') {
            return response()->json(['message' => 'Transaksi sudah selesai.'], 400);
        }

        $transaksi->status_transaksi = 'selesai';
        $transaksi->tanggal_diterima = Carbon::now(); // jika kolom tersedia
        $transaksi->save();

        return response()->json([
            'message' => 'Transaksi berhasil dikonfirmasi selesai.',
            'data' => $transaksi,
        ]);
    }


    public function storePenitipanBarang(Request $request)
    {
        $request->validate([
            'id_penitip' => 'required|exists:penitip,id_penitip',
            'id_barang' => 'required|exists:barang,id_barang',
            'id_qc' => 'required|exists:pegawai,id_pegawai',
        ]);

        $tanggalMasuk = Carbon::now();
        $tanggalAkhir = $tanggalMasuk->copy()->addDays(30);
        $batasPengambilan = $tanggalAkhir->copy()->addDays(7);

        $penitipan = Penitipan::create([
            'id_penitip' => $request->id_penitip,
            'id_barang' => $request->id_barang,
            'id_qc' => $request->id_qc,
            'tanggal_masuk' => $tanggalMasuk->toDateString(),
            'tanggal_akhir' => $tanggalAkhir->toDateString(),
            'batas_pengambilan' => $batasPengambilan->toDateString(),
            'status_perpanjangan' => 'tidak diperpanjang',
        ]);

        return response()->json([
            'message' => 'Penitipan barang berhasil ditambahkan.',
            'data' => $penitipan
        ]);
    }


    public function konfirmasiPengambilanKembali($id_barang)
    {
        $pegawai = Auth::user();

        if (!$pegawai || $pegawai->id_jabatan !== 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $detail = DetailPenitipan::with('penitipan.penitip')->where('id_barang', $id_barang)->first();

        if (!$detail) {
            return response()->json(['message' => 'Detail penitipan untuk barang ini tidak ditemukan.'], 404);
        }

        $penitipan = $detail->penitipan;

        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan.'], 404);
        }

        if ($penitipan->status_perpanjangan === 'diambil') {
            return response()->json(['message' => 'Barang sudah pernah diambil.'], 400);
        }

        $penitipan->status_perpanjangan = 'diambil kembali';
        $penitipan->batas_pengambilan = now();
        $penitipan->save();

        return response()->json([
            'message' => 'Barang berhasil dikonfirmasi telah diambil oleh penitip.',
            'data' => $penitipan,
        ]);
    }

    public function index()
    {

        $penitipan = Penitipan::with(['barang', 'penitip'])->get();
        return response()->json($penitipan);
    }

    public function storePenitipan(Request $request)
    {
        $request->validate([
            'id_penitip' => 'required|exists:penitip,id_penitip',
            'id_qc' => 'required|exists:pegawai,id_pegawai',
        ]);

        $pegawai = auth()->user();
        if (!$pegawai || $pegawai->id_jabatan != 7) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tanggalMasuk = now();
        $tanggalAkhir = now()->addDays(30);
        $batasPengambilan = $tanggalAkhir->copy()->addDays(7);

        $penitipan = Penitipan::create([
            'id_penitip' => $request->id_penitip,
            'id_pegawai' => $pegawai->id_pegawai,
            'id_qc' => $request->id_qc,
            'tanggal_masuk' => $tanggalMasuk,
            'tanggal_akhir' => $tanggalAkhir,
            'batas_pengambilan' => $batasPengambilan,
            'status_perpanjangan' => 'tidak diperpanjang',
        ]);

        return response()->json([
            'message' => 'Penitipan berhasil dibuat.',
            'data' => $penitipan,
        ]);
    }
    public function laporanBarangHabis(Request $request)
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->id_role !== 5) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $today = Carbon::today();

        $barangList = Barang::with(['penitipan.penitip'])
            ->whereHas('penitipan', function ($query) use ($today) {
                $query->where('tanggal_akhir', '<', $today);
            })
            ->get();

        $result = [];

        foreach ($barangList as $barang) {
            foreach ($barang->penitipan as $penitipan) {
                $result[] = [
                    'id_barang' => 'K' . $barang->id_barang ?? '???',
                    'nama_produk' => $barang->nama_barang,
                    'id_penitip' => 'T' . $penitipan->penitip->id_penitip ?? '-',
                    'nama_penitip' => $penitipan->penitip->nama_lengkap ?? '-',
                    'tanggal_masuk' => $penitipan->tanggal_masuk,
                    'tanggal_akhir' => $penitipan->tanggal_akhir,
                    'batas_ambil' => $penitipan->batas_pengambilan,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'tanggal_cetak' => now()->toDateString(),
        ]);
    }

    public function testKirimNotifikasi($id_penitip)
    {
        $penitip = Penitip::find($id_penitip);

        if (!$penitip || !$penitip->fcm_token) {
            return response()->json(['error' => 'Penitip tidak ditemukan atau token kosong'], 404);
        }

        sendFCMWithJWT(
            $penitip->fcm_token,
            '🔔 Ini Hanya Test',
            'Notifikasi ini dikirim tanpa filter tanggal penitipan.'
        );

        return response()->json(['message' => '✅ Notifikasi test dikirim']);
    }

    public function testNotifikasiTanggal()
    {
        $tanggalList = [
            now()->toDateString() => 'Hari ini adalah hari terakhir masa penitipan Anda.',
            now()->addDays(3)->toDateString() => 'Sisa 3 hari sebelum masa penitipan barang Anda berakhir.'
        ];

        $totalDikirim = 0;

        foreach ($tanggalList as $tanggal => $pesan) {
            $details = DetailPenitipan::with('penitipan.penitip')
                ->whereHas('penitipan', function ($query) use ($tanggal) {
                    $query->whereDate('tanggal_akhir', $tanggal);
                })
                ->get();

            foreach ($details as $detail) {
                $penitip = optional($detail->penitipan)->penitip;

                if ($penitip && $penitip->fcm_token) {
                    sendFCMWithJWT($penitip->fcm_token, '🔔 Notifikasi Test Tanggal', $pesan);
                    $totalDikirim++;
                }
            }
        }

        return response()->json([
            'message' => '✅ Test notifikasi H-3 & Hari-H dikirim',
            'total_dikirim' => $totalDikirim
        ]);
    }

    public function destroy($id)
    {
        $penitipan = Penitipan::findOrFail($id);

        foreach ($penitipan->detailpenitipan as $detail) {
            // Hapus semua foto barang
            foreach ($detail->barang->foto_barang as $foto) {
                $foto->delete();
            }

            $detail->barang->delete();
            $detail->delete();
        }

        $penitipan->delete();
        return response()->json(['message' => 'Penitipan berhasil dihapus.']);
    }

    public function getPenitipanTertentu()
    {
        $date = Carbon::now()->subDays(value: 30);

        $penitipan = Penitipan::with(['penitip', 'barang'])
            ->where('id_penitip', 10)
            ->whereHas('barang') 
            ->whereDate('tanggal_masuk', '>=', $date)
            ->orderBy('id_penitipan', 'desc')
            ->get();

        return response()->json($penitipan);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tanggal_masuk' => 'required|date',
            'tanggal_akhir' => 'required|date|after_or_equal:tanggal_masuk',
            'id_qc' => 'required|exists:pegawai,id_pegawai',
        ]);

        $penitipan = Penitipan::find($id);
        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan.'], 404);
        }

        $penitipan->tanggal_masuk = $request->tanggal_masuk;
        $penitipan->tanggal_akhir = $request->tanggal_akhir;
        $penitipan->id_qc = $request->id_qc;
        $penitipan->save();

        return response()->json([
            'message' => 'Data penitipan berhasil diperbarui.',
            'data' => $penitipan
        ]);
    }

    public function riwayatPenitipan()
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipanList = Penitipan::with(['detailpenitipan.barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        $barangList = [];

        foreach ($penitipanList as $penitipan) {
            foreach ($penitipan->detailpenitipan as $detail) {
                if ($detail->barang) {
                    $barang = $detail->barang;
                    $barangList[] = [
                        'id_barang' => $barang->id_barang,
                        'nama_barang' => $barang->nama_barang,
                        'deskripsi' => $barang->deskripsi,
                        'kategori_barang' => $barang->kategori_barang,
                        'harga_barang' => $barang->harga_barang,
                        'berat_barang' => $barang->berat_barang,
                        'status_barang' => $barang->status_barang,
                        'tanggal_masuk' => $penitipan->tanggal_masuk,
                        'rating_barang' => $barang->rating_barang,
                        'tanggal_garansi' => $barang->tanggal_garansi ?? null,
                        'foto_barang' => $barang->foto_barang->pluck('foto_barang'),
                    ];
                }
            }
        }

        usort($barangList, function ($a, $b) {
            return $b['id_barang'] <=> $a['id_barang'];
        });

        return response()->json([
            'message' => 'Riwayat penitipan berhasil diambil.',
            'data' => $barangList,
        ]);
    }


    public function sumbangBarangSukarela($id)
    {
        $user = Auth::user();
        if (!$user || $user->id_role !== 3) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        if ($barang->status_barang !== 'tersedia') {
            return response()->json(['message' => 'Barang tidak dapat disumbangkan. Status: ' . $barang->status_barang], 400);
        }

        // Ambil penitip dari relasi pivot barang → detailpenitipan → penitipan → penitip
        $detail = DetailPenitipan::where('id_barang', $barang->id_barang)->first();
        if (!$detail || !$detail->penitipan || !$detail->penitipan->penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan dari relasi barang'], 404);
        }

        $penitip = $detail->penitipan->penitip;

        // Hitung poin sosial: 1 poin per 10.000
        $poin = floor($barang->harga_barang / 10000);
        $penitip->poin_sosial += $poin;
        $penitip->save();

        // Update status barang
        $barang->status_barang = 'barang untuk donasi';
        $barang->save();

        return response()->json([
            'message' => 'Barang berhasil disumbangkan dan poin sosial diberikan',
            'poin_diberikan' => $poin,
            'penitip' => $penitip->nama_lengkap,
            'barang' => $barang->nama_barang,
        ]);
    }
}
