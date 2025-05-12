<?php

namespace App\Http\Controllers;

use App\Models\Organisasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class OrganisasiController extends Controller
{
    public function index()
    {
        if (auth()->user()->id_role != 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(Organisasi::all(), 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255',
            'nama_penerima' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'alamat' => 'required|string|max:500',
            'email' => 'required|email|unique:organisasi,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organisasi = Organisasi::create([
            'nama_organisasi' => $request->nama_organisasi,
            'nama_penerima' => $request->nama_penerima,
            'no_telepon' => $request->no_telepon,
            'alamat' => $request->alamat,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'id_role' => 4, // role default organisasi
        ]);

        return response()->json([
            'message' => 'Organisasi berhasil didaftarkan',
            'data' => $organisasi
        ], 201);
    }

    public function show($id)
    {
        if (auth()->user()->id_role != 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisasi = Organisasi::find($id);
        if (!$organisasi) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json($organisasi);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->id_role != 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisasi = Organisasi::find($id);
        if (!$organisasi) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'sometimes|required|string|max:255',
            'nama_penerima' => 'sometimes|required|string|max:255',
            'no_telepon' => 'sometimes|required|string|max:20',
            'alamat' => 'sometimes|required|string|max:500',
            'email' => "sometimes|required|email|unique:organisasi,email,{$id},id_organisasi",
            'password' => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['nama_organisasi', 'nama_penerima', 'no_telepon', 'alamat', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $organisasi->update($data);

        return response()->json([
            'message' => 'Organisasi berhasil diperbarui',
            'data' => $organisasi
        ]);
    }

    public function destroy($id)
    {
        if (auth()->user()->id_role != 6) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $organisasi = Organisasi::find($id);
        if (!$organisasi) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $organisasi->delete();
        return response()->json(['message' => 'Organisasi berhasil dihapus']);
    }
}
