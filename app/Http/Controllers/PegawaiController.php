<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Jabatan;
use App\Models\Transaksi;

class PegawaiController extends Controller
{
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
            'id_jabatan'     => 'nullable|integer|exists:jabatan,id_jabatan',
            'nama_lengkap'   => 'required|string|max:255',
            'alamat'         => 'required|string|max:255',
            'no_telepon'     => 'required|string|max:20',
            'email'          => 'required|email|unique:pegawai,email',
            'gender'         => 'required|in:Laki-laki,Perempuan',
            'tanggal_lahir'  => 'required|date',
            'password'       => 'required|string|min:6',
            'komisi_hunter'  => 'nullable|numeric|min:0',
            'image_user'     => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = $request->hasFile('image_user')
            ? $request->file('image_user')->store('pegawai/image_user', 'public')
            : null;

        $pegawai = Pegawai::create([
            'id_jabatan'     => $request->id_jabatan,
            'id_role'        => 1,
            'nama_lengkap'   => $request->nama_lengkap,
            'alamat'         => $request->alamat,
            'no_telepon'     => $request->no_telepon,
            'email'          => $request->email,
            'gender'         => $request->gender,
            'tanggal_lahir'  => $request->tanggal_lahir,
            'password'       => Hash::make($request->password),
            'komisi_hunter'  => 0,
            'image_user'     => $imagePath ?? 'default.jpg',
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
            'id_jabatan'     => 'nullable|integer|exists:jabatan,id_jabatan',
            'nama_lengkap'   => 'sometimes|required|string|max:255',
            'alamat'         => 'sometimes|required|string|max:255',
            'no_telepon'     => 'sometimes|required|string|max:20',
            'email'          => 'sometimes|required|email|unique:pegawai,email,' . $id . ',id_pegawai',
            'gender'         => 'sometimes|required|in:Laki-laki,Perempuan',
            'tanggal_lahir'  => 'sometimes|required|date',
            'password'       => 'nullable|string|min:6',
            'komisi_hunter'  => 'nullable|numeric|min:0',
            'image_user'     => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
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
            ->whereIn('status_transaksi', ['sedang disiapkan', 'dikirim'])
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
}
