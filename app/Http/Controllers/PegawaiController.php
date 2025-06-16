<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Pegawai;
use App\Models\Penitipan;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Jabatan;
use App\Models\Transaksi;

class PegawaiController extends Controller
{

    public function updateFcmTokenPegawai(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $pegawai = auth()->user(); // ambil pegawai dari token login

        if (!($pegawai instanceof \App\Models\Pegawai)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pegawai->fcm_token = $request->fcm_token;
        $pegawai->save();

        return response()->json(['message' => 'Token updated']);
    }


    public function index()
    {
        $pegawai = Pegawai::with(['jabatan', 'role'])->get();
        return response()->json($pegawai);
    }

    public function show($id)
    {
        $pegawai = Pegawai::with(['jabatan', 'role'])->findOrFail($id);
        return response()->json($pegawai);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_jabatan' => 'nullable|integer|exists:jabatan,id_jabatan',
            'nama_lengkap' => 'required|string|max:255',
            'alamat' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'email' => 'required|email|unique:pegawai,email',
            'gender' => 'required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'required|date',
            'password' => 'required|string|min:6',
            'komisi_hunter' => 'nullable|numeric|min:0',
            'image_user' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = $request->hasFile('image_user')
            ? $request->file('image_user')->store('pegawai/image_user', 'public')
            : null;

        $pegawai = Pegawai::create([
            'id_jabatan' => $request->id_jabatan,
            'id_role' => 1,
            'nama_lengkap' => $request->nama_lengkap,
            'alamat' => $request->alamat,
            'no_telepon' => $request->no_telepon,
            'email' => $request->email,
            'gender' => $request->gender,
            'tanggal_lahir' => $request->tanggal_lahir,
            'password' => Hash::make($request->password),
            'komisi_hunter' => 0,
            'image_user' => $imagePath ?? 'default.jpg',
        ]);

        return response()->json([
            'message' => 'Pegawai berhasil ditambahkan',
            'data' => $pegawai,
            'image_url' => $imagePath ? asset('storage/' . $imagePath) : null,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'id_jabatan' => 'nullable|integer|exists:jabatan,id_jabatan',
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'alamat' => 'sometimes|required|string|max:255',
            'no_telepon' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|unique:pegawai,email,' . $id . ',id_pegawai',
            'gender' => 'sometimes|required|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'sometimes|required|date',
            'password' => 'nullable|string|min:6',
            'komisi_hunter' => 'nullable|numeric|min:0',
            'image_user' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image_user')) {
            if ($pegawai->image_user) {
                Storage::disk('public')->delete($pegawai->image_user);
            }
            $pegawai->image_user = $request->file('image_user')->store('pegawai/image_user', 'public');
        }

        $data = $request->except(['password', 'image_user']);
        $data['id_role'] = 1;

        $pegawai->fill($data);

        if ($request->filled('password')) {
            $pegawai->password = Hash::make($request->password);
        }

        $pegawai->save();

        return response()->json([
            'message' => 'Pegawai berhasil diperbarui',
            'data' => $pegawai,
            'image_url' => $pegawai->image_user ? asset('storage/' . $pegawai->image_user) : null,
        ]);
    }

    public function destroy($id)
    {
        $pegawai = Pegawai::findOrFail($id);

        if ($pegawai->image_user) {
            Storage::disk('public')->delete($pegawai->image_user);
        }

        $pegawai->delete();

        return response()->json(['message' => 'Pegawai berhasil dihapus']);
    }

    public function transaksiGudang(Request $request)
    {
        $pegawai = auth()->user();

        if (!$pegawai || $pegawai->jabatan !== 'pegawai gudang') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with(['pembeli', 'barang'])
            ->whereIn('status_transaksi', ['disiapkan', 'dikirim'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transaksi);
    }

    public function getDaftarPegawai()
    {
        $pegawai = Pegawai::with(['jabatan', 'role'])
            ->where('id_role', 1)
            ->get();

        return response()->json($pegawai);
    }

    public function getListKurir()
    {
        $kurir = Pegawai::where('id_jabatan', 2)->get();
        return response()->json(['kurir' => $kurir]);
    }

    public function getHunter()
    {
        $hunters = Pegawai::where('id_jabatan', 5)->select('id_pegawai', 'nama_lengkap')->get();

        return response()->json([
            'message' => 'Daftar hunter berhasil diambil.',
            'data' => $hunters,
        ]);
    }

    public function getQc()
    {
        $pegawai = auth()->user();

        if (!$pegawai || !in_array($pegawai->id_jabatan, [1, 7])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $qcList = Pegawai::where('id_jabatan', 8)->get();

        return response()->json([
            'message' => 'Daftar QC berhasil diambil.',
            'data' => $qcList
        ]);
    }

    public function showDetailHunterWithKomisiHistory()
    {
        $user = Auth::user();

        if (!$user || $user->id_jabatan != 5) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $history = Transaksi::with([
            'detailTransaksi.barang.detailpenitipan.penitipan.penitip',
            'detailTransaksi.barang.pegawai',
        ])
            ->where('status_transaksi', 'selesai')
            ->orderBy('tanggal_pelunasan', 'asc')
            ->get()
            ->flatMap(function ($transaksi) use ($user) {
                return $transaksi->detailTransaksi
                    ->filter(function ($detail) use ($user) {
                        return $detail->barang &&
                            $detail->barang->id_pegawai === $user->id_pegawai &&
                            strtolower($detail->barang->status_barang) === 'terjual';
                    })
                    ->map(function ($detail) use ($transaksi) {
                        $barang = $detail->barang;
                        $harga = $barang->harga_barang ?? 0;
                        $penitipan = $barang->detailpenitipan->penitipan ?? null;

                        return [
                            'id_transaksi' => $transaksi->id_transaksi,
                            'tanggal' => $transaksi->tanggal_pelunasan,
                            'nama_barang' => $barang->nama_barang ?? '-',
                            'harga_barang' => $harga,
                            'nama_penitip' => $penitipan?->penitip?->nama_lengkap ?? '-',
                            'komisi' => round($harga * 0.05),
                        ];
                    });
            })->values();

        // Hitung running total berdasar komisi sebelumnya
        $komisiAwal = $user->komisi_hunter - $history->sum('komisi');
        $runningTotal = $komisiAwal;

        $history = $history->map(function ($item) use (&$runningTotal) {
            $runningTotal += $item['komisi'];
            $item['komisi_hunter_saat_itu'] = $runningTotal;
            return $item;
        });

        return response()->json([
            'id_pegawai' => $user->id_pegawai,
            'nama_lengkap' => $user->nama_lengkap,
            'komisi_total' => $user->komisi_hunter,
            'komisi_history' => $history,
        ]);
    }



    public function getDetailKomisiHunter($id_transaksi)
{
    $user = Auth::user();

    if (!$user || $user->id_jabatan != 5) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $transaksi = Transaksi::with([
        'pembeli',
        'detailTransaksi.barang.foto_barang',
        'detailTransaksi.barang.detailpenitipan.penitipan.penitip'
    ])->findOrFail($id_transaksi);

    $detail = [];
    $totalKomisi = 0;

    foreach ($transaksi->detailTransaksi as $dt) {
        $barang = $dt->barang;

        if (
            $barang &&
            $barang->id_pegawai == $user->id_pegawai &&
            strtolower($barang->status_barang) === 'terjual'
        ) {
            $fotoUrls = $barang->foto_barang->map(function ($foto) {
                return asset('storage/' . $foto->foto_barang);
            });

            $penitip = optional($barang->detailpenitipan->penitipan->penitip);

            $komisi = round(($barang->harga_barang ?? 0) * 0.05);
            $totalKomisi += $komisi;

            $detail[] = [
                'nama_barang' => $barang->nama_barang ?? '-',
                'harga_barang' => $barang->harga_barang ?? 0,
                'kategori' => $barang->kategori_barang ?? '-',
                'penitip' => $penitip->nama_lengkap ?? '-',
                'foto_barang' => $fotoUrls,
                'komisi' => $komisi,
            ];
        }
    }

    return response()->json([
        'id_transaksi' => $transaksi->id_transaksi,
        'tanggal' => $transaksi->tanggal_pelunasan,
        'total_harga' => $transaksi->total_harga,
        'status' => $transaksi->status_transaksi,
        'pembeli' => optional($transaksi->pembeli)->nama_lengkap ?? '-',
        'komisi_anda' => $totalKomisi,
        'detail_komisi' => $detail
    ]);
}








    public function daftarKomisi()
    {
        $idHunter = 5;
        $tanggal = Carbon::now()->subDays(30)->toDateString();

        $pegawai = Pegawai::with('jabatan')->find($idHunter);

        if (!$pegawai || $pegawai->id_jabatan != 5) {
            return response()->json(['message' => 'Pegawai bukan seorang hunter.'], 403);
        }

        $data = Barang::with(['pegawai'])
            ->where('id_pegawai', $idHunter)
            ->where('created_at', '>=', $tanggal)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($barang) {
                return [
                    'nama_barang' => $barang->nama_barang,
                    'tanggal_masuk' => date('d/m/Y', strtotime($barang->created_at)),
                    'status_barang' => $barang->status_barang,
                    'komisi_hunter' => round(($barang->harga_barang ?? 0) * 0.05),
                    'nama_hunter' => $barang->pegawai->nama_lengkap ?? '-',
                ];
            });

        return response()->json(['data' => $data]);
    }
}
