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
        // $pembeli = Auth::user();
        // return Alamat::where('id_pembeli', $pembeli->id_pembeli)->get();
        return Alamat::all();
    }

    // POST /api/alamat → buat alamat baru untuk user yang login
    public function store(Request $request)
    {
        $request->validate([
            'provinsi' => 'required|string|max:100',
            'kelurahan' => 'required|string|max:100',
            'kecamatan' => 'required|string|max:100',
            'detail_alamat' => 'required|string',
            'kode_pos' => 'required|string|max:10',
            'id_pembeli' => 'required|exists:pembeli,id_pembeli',
        ]);

        $alamat = Alamat::create([
            'provinsi' => $request->provinsi,
            'kelurahan' => $request->kelurahan,
            'kecamatan' => $request->kecamatan,
            'detail_alamat' => $request->detail_alamat,
            'kode_pos' => $request->kode_pos,
            'id_pembeli' => $request->id_pembeli,
            // 'id_pembeli'     => Auth::user()->id_pembeli, // otomatis dari user yang login
        ]);

        return response()->json(['message' => 'Alamat berhasil dibuat', 'data' => $alamat], 201);
    }

    // GET /api/alamat/{id} → hanya jika milik user
    public function show($id)
    {
        // $alamat = Alamat::where('id_alamat', $id)
        //     ->where('id_pembeli', Auth::user()->id_pembeli)
        //     ->firstOrFail();

        // return response()->json($alamat);

        $alamat = Alamat::with('pembeli')->find($id);

        if (!$alamat) {
            return response()->json([
                'message' => 'Alamat tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'message' => 'Detail alamat ditemukan',
            'data' => $alamat
        ]);
    }

    // PUT /api/alamat/{id}
    public function update(Request $request, $id)
    {
        // $alamat = Alamat::where('id_alamat', $id)
        //     ->where('id_pembeli', Auth::user()->id_pembeli)
        //     ->firstOrFail();

        // $request->validate([
        //     'alamat_lengkap' => 'sometimes|required|string',
        //     'kode_pos' => 'sometimes|required|string|max:10',
        //     'kecamatan' => 'sometimes|required|string',
        //     'kota' => 'sometimes|required|string',
        //     'provinsi' => 'sometimes|required|string',
        // ]);

        // $alamat->update($request->all());

        // return response()->json(['message' => 'Alamat berhasil diperbarui', 'data' => $alamat]);

        $alamat = Alamat::findOrFail($id);

        $request->validate([
            'provinsi' => 'sometimes|required|string|max:100',
            'kelurahan' => 'sometimes|required|string|max:100',
            'kecamatan' => 'sometimes|required|string|max:100',
            'detail_alamat' => 'sometimes|required|string',
            'kode_pos' => 'sometimes|required|string|max:10',
            // 'id_pembeli'  => dilarang diubah
        ]);

        // Pastikan id_pembeli tidak bisa diubah lewat request
        $requestData = $request->except(['id_pembeli']);

        $alamat->update($requestData);

        return response()->json([
            'message' => 'Alamat berhasil diperbarui',
            'data' => $alamat
        ]);
    }

    // DELETE /api/alamat/{id}
    public function destroy($id)
    {
        // $alamat = Alamat::where('id_alamat', $id)
        //     ->where('id_pembeli', Auth::user()->id_pembeli)
        //     ->firstOrFail();

        // $alamat->delete();

        // return response()->json(['message' => 'Alamat berhasil dihapus']);

        $alamat = Alamat::findOrFail($id);
        $alamat->delete();

        return response()->json([
            'message' => 'Alamat berhasil dihapus'
        ]);
    }
}
