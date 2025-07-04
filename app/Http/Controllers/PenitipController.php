<?php

namespace App\Http\Controllers;

use App\Models\Penitip;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;


class PenitipController extends Controller
{
    public function updateFcmTokenPenitip(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $penitip = auth()->user(); // ambil penitip dari token login

        if (!($penitip instanceof \App\Models\Penitip)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }


        $penitip->fcm_token = $request->fcm_token;
        $penitip->save();

        return response()->json(['message' => 'Token updated']);
    }


    public function index()
    {
        $penitip = Penitip::all();

        return response()->json([
            'message' => 'Daftar semua penitip',
            'data' => $penitip
        ]);
    }
    // ✅ Register Penitip
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'badge' => 'required|string|max:255',
            'nama_lengkap' => 'required|string|max:255',
            'gender' => 'required|in:L,P',
            'email' => 'required|email|unique:penitip,email',
            'no_telepon' => 'required|string|min:10',
            'password' => 'required|string|min:6',
            'tanggal_lahir' => 'required|date',
            'poin_sosial' => 'nullable|integer|min:0',
            'saldo' => 'nullable|numeric|min:0',
            'bonus' => 'required|numeric',
            'no_ktp' => 'required|string|max:50|unique:penitip,no_ktp',
            'image_user' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'foto_ktp' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle image uploads
        $imageUserName = $request->hasFile('image_user')
            ? time() . '_image_' . preg_replace('/\s+/', '_', $request->file('image_user')->getClientOriginalName())
            : 'default.jpg';

        $fotoKtpName = $request->hasFile('foto_ktp')
            ? time() . '_ktp_' . preg_replace('/\s+/', '_', $request->file('foto_ktp')->getClientOriginalName())
            : null;

        if ($request->hasFile('image_user')) {
            $request->file('image_user')->move(public_path('storage/foto_penitip'), $imageUserName);
        }

        if ($request->hasFile('foto_ktp')) {
            $request->file('foto_ktp')->move(public_path('storage/foto_ktp'), $fotoKtpName);
        }

        $penitip = Penitip::create([
            'badge' => $request->badge,
            'nama_lengkap' => $request->nama_lengkap,
            'gender' => $request->gender,
            'email' => $request->email,
            'no_telepon' => $request->no_telepon,
            'password' => Hash::make($request->password),
            'tanggal_lahir' => $request->tanggal_lahir,
            'poin_sosial' => $request->poin_sosial ?? 0,
            'saldo' => $request->saldo ?? 0,
            'bonus' => $request->bonus,
            'no_ktp' => $request->no_ktp,
            'image_user' => $imageUserName,
            'foto_ktp' => $fotoKtpName,
            'id_role' => 3,
        ]);

        return response()->json([
            'message' => 'Registrasi penitip berhasil',
            'data' => $penitip
        ], 201);
    }

    public function show($id)
    {
        $penitip = Penitip::find($id);
        if (!$penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan.'], 404);
        }
        return response()->json($penitip);
    }

    // ✅ Profile Penitip
    public function profile(Request $request)
    {
        $penitip = Penitip::find(auth()->id());

        if (!$penitip) {
            return response()->json(['message' => 'Data penitip tidak ditemukan.'], 404);
        }

        return response()->json($penitip);
    }

    // ✅ Update Profil Penitip
    public function update(Request $request, $id)
    {
        $penitip = Penitip::find($id);

        if (!$penitip) {
            return response()->json(['message' => 'Data penitip tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|email',
            'password' => 'nullable|string|min:6',
            'tanggal_lahir' => 'sometimes|date',
            'no_telepon' => 'sometimes|string|max:20',
            'gender' => 'sometimes|in:L,P',
            'image_user' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle image_user upload
        if ($request->hasFile('image_user')) {
            // Hapus foto lama kalau ada
            if ($penitip->image_user && $penitip->image_user !== 'default.jpg') {
                Storage::disk('public')->delete('foto_penitip/' . $penitip->image_user);
            }

            // Upload baru
            $filename = time() . '_image_' . preg_replace('/\s+/', '_', $request->file('image_user')->getClientOriginalName());
            $path = $request->file('image_user')->storeAs('foto_penitip', $filename, 'public');
            $penitip->image_user = basename($path);
        }

        // Update field lain
        $penitip->fill($request->except(['password', 'image_user']));

        // Jika password diisi
        if ($request->filled('password')) {
            $penitip->password = Hash::make($request->password);
        }

        $penitip->save();

        return response()->json([
            'message' => 'Data penitip berhasil diperbarui.',
            'data' => $penitip,
            'image_url' => $penitip->image_user ? asset('storage/foto_penitip/' . $penitip->image_user) : null,
        ]);
    }

    // ❌ Delete Penitip
    public function destroy($id)
    {
        $penitip = Penitip::find($id);

        if (!$penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan.'], 404);
        }

        if ($penitip->image_user && $penitip->image_user !== 'default.jpg') {
            $imagePath = public_path('storage/foto_penitip/' . $penitip->image_user);
            if (file_exists($imagePath))
                unlink($imagePath);
        }

        if ($penitip->foto_ktp) {
            $ktpPath = public_path('storage/foto_ktp/' . $penitip->foto_ktp);
            if (file_exists($ktpPath))
                unlink($ktpPath);
        }

        $penitip->delete();

        return response()->json(['message' => 'Penitip berhasil dihapus.']);
    }

    // 🔍 Search Penitip by nama/email/telepon
    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['message' => 'Query pencarian kosong.'], 400);
        }

        $results = Penitip::where('nama_lengkap', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('no_telepon', 'like', "%{$query}%")
            ->get();

        return response()->json([
            'message' => 'Hasil pencarian penitip',
            'data' => $results
        ]);
    }

    public function showbarang(Request $request)
    {
        $penitip = auth()->user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $barang = Barang::with('foto_barang')
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        return response()->json($barang);
    }


    public function barangPenitip()
    {
        $penitip = auth()->user();

        if (!$penitip || !$penitip instanceof Penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $barang = Barang::with('foto_barang')
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        return response()->json($barang);
    }

    public function getAllPenitip()
    {
        $penitip = Penitip::select('id_penitip', 'nama_lengkap')->get();
        return response()->json([
            'message' => 'Daftar penitip berhasil diambil.',
            'data' => $penitip
        ]);
    }

    public function generateTopSeller()
    {
        $user = Auth::user();
        if (!$user || $user->id_role != 6) {
            return response()->json(['message' => 'Unauthorized. Hanya admin yang dapat mengakses fitur ini.'], 403);
        }

        $start = Carbon::now()->startOfMonth()->subMonth();
        $end = Carbon::now()->startOfMonth()->subSecond();

        //Hitung total transaksi selesai per penitip
        $topData = DB::table('transaksi')
            ->join('detailtransaksi', 'transaksi.id_transaksi', '=', 'detailtransaksi.id_transaksi')
            ->join('barang', 'detailtransaksi.id_barang', '=', 'barang.id_barang')
            ->join('detailpenitipan', 'barang.id_barang', '=', 'detailpenitipan.id_barang')
            ->join('penitipan', 'detailpenitipan.id_penitipan', '=', 'penitipan.id_penitipan')
            ->join('penitip', 'penitipan.id_penitip', '=', 'penitip.id_penitip')
            ->where('transaksi.status_transaksi', 'selesai')
            ->whereBetween('transaksi.tanggal_pelunasan', [$start, $end])
            ->select('penitip.id_penitip', DB::raw('SUM(barang.harga_barang) as total_penjualan'))
            ->groupBy('penitip.id_penitip')
            ->orderByDesc('total_penjualan')
            ->first();

        if (!$topData) {
            return response()->json(['message' => 'Tidak ada transaksi selesai bulan lalu'], 200);
        }

        //Reset badge semua penitip
        Penitip::query()->update(['badge' => 'Regular']);

        $penitip = Penitip::find($topData->id_penitip);
        if ($penitip) {
            $penitip->badge = 'Top Seller';
            $penitip->bonus = ($penitip->bonus ?? 0) + ($topData->total_penjualan * 0.01);
            $penitip->save();
        }

        return response()->json([
            'message' => 'Top Seller berhasil ditentukan.',
            'penitip' => $penitip
        ], 200);
    }

    public function tarikSaldo(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->id_role != 3) {
            return response()->json(['message' => 'Unauthorized. Hanya admin yang dapat mengakses fitur ini.'], 403);
        }

        $penitip = Penitip::find($user->id_penitip);
        if (!$penitip) {
            return response()->json(['message' => 'Penitip tidak ditemukan.'], 404);
        }

        $penarikan = $request->nominal_tarik * 0.05;

        if ($penarikan > $penitip->saldo) {
            return response()->json(['message' => 'Saldo tidak mencukupi.'], 400);
        }

        $penitip->saldo -= $penarikan;
        $penitip->save();

        return response()->json([
            'message' => 'Saldo berhasil ditarik.',
            'penitip' => $penitip
        ], 200);
    }
}
