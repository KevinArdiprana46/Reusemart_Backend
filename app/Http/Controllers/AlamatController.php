<?php

namespace App\Http\Controllers;

use App\Models\Alamat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlamatController extends Controller
{
    // GET /api/alamat → hanya alamat milik user yang login
    public function index()
    {
        return Alamat::where('id_pembeli', Auth::user()->id_pembeli)->get();
    }

    // POST /api/alamat → buat alamat baru
    public function store(Request $request)
    {
        $request->validate([
            'kelurahan' => 'required|string|max:100',
            'kecamatan' => 'required|string|max:100',
            'detail_alamat' => 'required|string',
            'kode_pos' => 'required|max:10',
        ]);
        try {
            $alamat = Alamat::create([
                'provinsi' => 'DIY Yogyakarta',
                'kelurahan' => $request->kelurahan,
                'kecamatan' => $request->kecamatan,
                'detail_alamat' => $request->detail_alamat,
                'kode_pos' => $request->kode_pos,
                'id_pembeli' => Auth::user()->id_pembeli,
            ]);

            return response()->json([
                'message' => 'Alamat berhasil ditambahkan.',
                'data' => $alamat
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan alamat.',
                'error' => $e->getMessage()
            ], 500);
        }


    }

    public function show()
    {
        return Alamat::where('id_pembeli', Auth::user()->id_pembeli)->get();
    }


    // PUT /api/alamat/{id}
    public function update(Request $request, $id)
    {
        $alamat = Alamat::where('id_alamat', $id)
            ->where('id_pembeli', Auth::user()->id_pembeli)
            ->firstOrFail();

        $request->validate([
            'kelurahan' => 'sometimes|required|string|max:100',
            'kecamatan' => 'sometimes|required|string|max:100',
            'detail_alamat' => 'sometimes|required|string',
            'kode_pos' => 'sometimes|required|max:10',
        ]);

        $alamat->update($request->only([
            'kelurahan',
            'kecamatan',
            'detail_alamat',
            'kode_pos'
        ]));

        return response()->json([
            'message' => 'Alamat berhasil diperbarui.',
            'data' => $alamat
        ]);
    }

    // DELETE /api/alamat/{id}
    public function destroy($id)
    {
        $alamat = Alamat::where('id_alamat', $id)
            ->where('id_pembeli', Auth::user()->id_pembeli)
            ->firstOrFail();

        $alamat->delete();

        return response()->json(['message' => 'Alamat berhasil dihapus.']);
    }
}
