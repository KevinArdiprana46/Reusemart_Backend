<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\FotoBarang;
use App\Models\Penitip;
use App\Models\Penitipan;
use App\Models\DetailPenitipan;
use App\Models\DetailTransaksi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    public function index()
    {
        $barang = Barang::with('foto_barang')->get();
        return response()->json($barang);
    }
    public function show($id)
    {
        $barang = Barang::with('foto_barang', 'detailpenitipan.penitipan.penitip')->find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }
        return response()->json($barang);
    }

    public function getBarangRekomendasi()
    {
        $barang = Barang::with('foto_barang', 'detailpenitipan')
            ->where('status_barang', 'tersedia')
            ->orderBy('id_barang', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'barang' => $barang,
        ]);
    }

    public function getByKategori($kategori)
    {
        $barang = Barang::with('foto_barang')
            ->where('kategori_barang', $kategori)
            ->where('status_barang', 'tersedia')
            ->get();

        return response()->json(['barang' => $barang]);
    }




    public function store(Request $request)
    {
        $request->validate([
            'id_penitip' => 'required|exists:penitip,id_penitip',
            'nama_barang' => 'required|string|max:255',
            'kategori_barang' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'harga_barang' => 'required|numeric|min:0',
            'berat_barang' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:1',
            'status_barang' => 'required|string|in:tersedia,habis,tidak tersedia',
            'tanggal_garansi' => 'nullable|date',
            'foto_barang.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $barang = Barang::create([
            'id_pegawai' => $request->id_pegawai,
            'nama_barang' => $request->nama_barang,
            'kategori_barang' => $request->kategori_barang,
            'deskripsi' => $request->deskripsi,
            'harga_barang' => $request->harga_barang,
            'berat_barang' => $request->berat_barang,
            'stock' => $request->stock,
            'status_barang' => $request->status_barang,
            'tanggal_garansi' => $request->tanggal_garansi,
            'created_at' => now(),
        ]);

        if ($request->hasFile('foto_barang')) {
            foreach ($request->file('foto_barang') as $file) {
                $path = $file->store('foto_barang', 'public');

                FotoBarang::create([
                    'id_barang' => $barang->id_barang,
                    'foto_barang' => $path,
                ]);
            }
        }

        return response()->json([
            'message' => 'Barang berhasil ditambahkan.',
            'data' => $barang
        ]);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_barang' => 'required|string|max:255',
            'kategori_barang' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'harga_barang' => 'required|numeric|min:0',
            'berat_barang' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:1',
            'status_barang' => 'required|string|in:tersedia,habis,tidak tersedia',
            'tanggal_garansi' => 'nullable|date',
            'foto_barang.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $barang = Barang::findOrFail($id);

        $barang->update([
            'nama_barang' => $request->nama_barang,
            'kategori_barang' => $request->kategori_barang,
            'deskripsi' => $request->deskripsi,
            'harga_barang' => $request->harga_barang,
            'stock' => $request->stock,
            'status_barang' => $request->status_barang,
            'tanggal_garansi' => $request->tanggal_garansi,
        ]);

        if ($request->has('foto_hapus')) {
            foreach ($request->foto_hapus as $idFoto) {
                $foto = FotoBarang::find($idFoto);
                if ($foto) {
                    Storage::disk('public')->delete($foto->foto_barang);
                    $foto->delete();
                }
            }
        }

        if ($request->hasFile('foto_barang')) {
            foreach ($request->file('foto_barang') as $file) {
                $path = $file->store('foto_barang', 'public');

                FotoBarang::create([
                    'id_barang' => $barang->id_barang,
                    'foto_barang' => $path,
                ]);
            }
        }

        return response()->json([
            'message' => 'Barang berhasil diperbarui.',
            'data' => $barang->load('foto_barang'),
        ]);
    }


    public function destroy($id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }

        $barang->delete();
        return response()->json(['message' => 'Barang deleted successfully']);
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        $result = Barang::where('nama_barang', 'like', "$query%")
            ->get();

        return response()->json($result);
    }

    public function getBarangDonasi(Request $request)
    {
        $kategori = $request->header('X-Kategori-Barang');
        $query = Barang::where('status_barang', 'donasi')
            ->where('stock', '>', 0);

        if ($kategori) {
            $query->where('kategori_barang', $kategori);
        }

        $result = $query->get();

        return response()->json($result);
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

        $barangList = [];

        foreach ($penitipanList as $penitipan) {
            foreach ($penitipan->detailpenitipan as $detail) {
                if (
                    strtolower(trim($detail->barang->kategori_barang)) === strtolower(trim($kategori))
                ) {
                    $barang = $detail->barang;
                    $barang->id_penitipan = $penitipan->id_penitipan; // Tambahkan informasi penitipan
                    $barangList[] = $barang;
                }
            }
        }

        if (empty($barangList)) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan untuk kategori ini.',
                'kategori_yang_dicari' => $kategori
            ], 404);
        }

        return response()->json($barangList);
    }

    public function showNon($id)
    {
        $barang = Barang::find($id);
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }
        return response()->json($barang);
    }

    public function getNonByKategori($kategori)
    {
        // Mengambil barang berdasarkan kategori tanpa autentikasi
        $barang = Barang::with('foto_barang')
            ->whereRaw('LOWER(TRIM(kategori_barang)) = ?', [strtolower(trim($kategori))])
            ->get();

        if ($barang->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada barang ditemukan',
                'kategori_yang_dicari' => $kategori
            ], 404);
        }

        return response()->json($barang);
    }



    public function getAllBarangForPegawai(Request $request)
    {
        $pegawai = $request->user();

        if (!$pegawai || $pegawai->id_pegawai === null) {
            return response()->json(['message' => 'Pegawai tidak ditemukan atau belum login'], 403);
        }

        $barang = Barang::with([
            'foto_barang',
            'detailpenitipan.penitipan.penitip'
        ])
            ->withCount([
                'diskusi as jumlah_chat_baru' => function ($q) {
                    $q->where('is_read', false);
                }
            ])
            ->where('status_barang', 'tersedia')
            ->whereHas('diskusi') // hanya barang yang punya diskusi
            ->get();

        return response()->json([
            'barang' => $barang,
            'id_jabatan' => $pegawai->id_jabatan,
        ]);
    }


    public function getAllNonBarangForPegawai()
    {
        $barang = Barang::with('foto_barang')->get();

        return response()->json([
            'barang' => $barang,

        ]);
    }

    public function getBarangTerjual()
    {
        $barang = Barang::where('status_barang', 'terjual')
            ->get();

        return response()->json($barang);
    }

    public function uploadFotoBarang(Request $request, $id)
    {
        $request->validate([
            'foto_barang' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $imagePath = $request->file('foto_barang')->store('foto_barang', 'public');

        $foto_barang = FotoBarang::create([
            'id_barang' => $id,
            'foto_barang' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Foto berhasil ditambahkan',
            'image_url' => asset('storage/' . $imagePath),
        ], 201);
    }


    public function beriRatingBarang(Request $request, $id)
    {
        $request->validate([
            'rating_barang' => 'required|integer|min:1|max:5',
        ]);

        // ambil barang beserta relasi penitipan dan transaksi
        $barang = Barang::with([
            'detailpenitipan.penitipan',
            'detailtransaksi.transaksi'
        ])->find($id);

        if (!$barang) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        // validasi apakah transaksi untuk barang ini selesai
        $transaksiSelesai = DetailTransaksi::where('id_barang', $id)
            ->whereHas('transaksi', function ($query) {
                $query->where('status_transaksi', 'selesai');
            })
            ->exists();

        if (!$transaksiSelesai) {
            return response()->json([
                'message' => 'Rating hanya bisa diberikan jika transaksi barang ini sudah selesai.'
            ], 403);
        }

        $barang->rating_barang = $request->rating_barang;
        $barang->save();

        // ambil id_penitip dari relasi pivot
        $penitipan = $barang->detailpenitipan->penitipan ?? null;
        if (!$penitipan) {
            return response()->json(['message' => 'Data penitipan tidak ditemukan'], 404);
        }

        $this->hitungRatingPenitip($penitipan->id_penitip);

        return response()->json([
            'message' => 'Rating berhasil disimpan.',
            'barang' => $barang
        ]);
    }


    public function hitungRatingPenitip($id_penitip)
    {
        // ambil semua barang dari penitip ini melalui penitipan → detailpenitipan → barang
        $barangIds = DetailPenitipan::whereHas('penitipan', function ($query) use ($id_penitip) {
            $query->where('id_penitip', $id_penitip);
        })->pluck('id_barang');

        $rataRata = Barang::whereIn('id_barang', values: $barangIds)
            ->where('status_barang', 'sold out')
            ->whereNotNull('rating_barang')
            ->avg('rating_barang');

        $penitip = Penitip::find($id_penitip);
        if (!$penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan'], 404);
        }

        $penitip->rating_penitip = $rataRata ? round($rataRata, 2) : 0;
        $penitip->save();

        return response()->json([
            'message' => 'Rating penitip berhasil diperbarui.',
            'rating_penitip' => $penitip->rating_penitip
        ]);
    }


    public function getDetailBarang($id)
    {
        $barang = Barang::with([
            'foto_barang',
            'detailpenitipan.penitipan'
        ])->find($id);

        if (!$barang) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Detail barang berhasil diambil',
            'data' => $barang,
        ]);
    }

    public function storeBarangDalamPenitipan(Request $request, $id_penitipan)
    {
        $request->validate([
            'id_pegawai' => 'nullable|exists:pegawai,id_pegawai',
            'nama_barang' => 'required|string|max:255',
            'kategori_barang' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'harga_barang' => 'required|numeric|min:0',
            'berat_barang' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:1',
            'status_barang' => 'required|string|in:tersedia,habis,tidak tersedia',
            'tanggal_garansi' => 'nullable|date',
            'foto_barang.*' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        Log::info('🔥 ID Pegawai dari request:', [$request->id_pegawai]);

        try {
            // Simpan barang
            $barang = Barang::create([
                'id_pegawai' => $request->id_pegawai,
                'nama_barang' => $request->nama_barang,
                'kategori_barang' => $request->kategori_barang,
                'deskripsi' => $request->deskripsi,
                'harga_barang' => $request->harga_barang,
                'berat_barang' => $request->berat_barang,
                'stock' => $request->stock,
                'status_barang' => $request->status_barang,
                'tanggal_garansi' => $request->tanggal_garansi,
                'created_at' => now(),
            ]);

            if ($request->hasFile('foto_barang')) {
                $fotoInput = $request->file('foto_barang');

                // Paksa array kalau single upload
                $files = is_array($fotoInput) ? $fotoInput : [$fotoInput];

                foreach ($files as $file) {
                    $path = $file->store('foto_barang', 'public');
                    FotoBarang::create([
                        'id_barang' => $barang->id_barang,
                        'foto_barang' => $path,
                    ]);
                }
            }
            // Buat relasi di detailpenitipan
            DetailPenitipan::create([
                'id_penitipan' => $id_penitipan,
                'id_barang' => $barang->id_barang,
            ]);
            $barang->load('foto_barang');

            return response()->json([
                'message' => 'Barang berhasil ditambahkan ke penitipan.',
                'barang' => $barang,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menyimpan barang.', 'error' => $e->getMessage()], 500);
            Log::error("❌ Gagal store barang", ['exception' => $e]);
        }
    }

    public function stokGudang()
    {
        $user = Auth::user();

        // Cek apakah role = 1 (Owner)
        if ($user->id_role !== 5) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya Owner yang dapat melihat laporan ini.'
            ], 403);
        }

        $barang = Barang::with(['pegawai', 'detailpenitipan.penitipan.penitip'])
            ->where('status_barang', 'tersedia')
            ->whereHas('detailpenitipan') // hanya ambil barang yang punya detailpenitipan
            ->get();

        $result = $barang->map(function ($b) {
            $detail = optional($b->detailpenitipan)->where('id_barang', $b->id_barang)->first();
            $penitipan = optional($detail)->penitipan;
            $penitip = optional($penitipan)->penitip;
            $hunter = optional($b->pegawai);

            return [
                'kode_produk'     => $b->id_barang,
                'nama_produk'     => $b->nama_barang,
                'id_penitip'      => $penitip->id_penitip ?? '-',
                'nama_penitip'    => $penitip->nama_lengkap ?? '-',
                'tanggal_masuk'   => $penitipan && $penitipan->tanggal_masuk
                    ? \Carbon\Carbon::parse($penitipan->tanggal_masuk)->format('d/m/Y')
                    : '-',
                'perpanjangan'    => match ($penitipan->status_perpanjangan ?? null) {
                    'diperpanjang' => 'Ya',
                    'tidak diperpanjang' => 'Tidak',
                    default => '-',
                },
                'id_hunter' => $hunter?->id_pegawai,
                'nama_hunter'     => $hunter->nama_lengkap ?? '-',
                'harga'           => $b->harga_barang,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function konversiDonasi()
    {
        $now = Carbon::now();

        // Ambil barang yang masih "tersedia" dan melewati batas pengambilan
        $data = DB::table('barang')
            ->join('detailpenitipan', 'barang.id_barang', '=', 'detailpenitipan.id_barang')
            ->join('penitipan', 'detailpenitipan.id_penitipan', '=', 'penitipan.id_penitipan')
            ->where('barang.status_barang', 'expired')
            ->whereDate('penitipan.batas_pengambilan', '<', $now)
            ->update(['barang.status_barang' => 'donasi']);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
