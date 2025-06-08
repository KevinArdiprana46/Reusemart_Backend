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
        $user = $request->user(); // asumsi autentikasi sudah dilakukan
        $data = $request->validate([
            'kecamatan' => 'required|string',
            'kelurahan' => 'required|string',
            'detail_alamat' => 'required|string',
            'kode_pos' => 'required|string',
        ]);

        // Cek apakah user belum punya alamat utama
        $sudahAdaUtama = Alamat::where('id_pembeli', $user->id_pembeli)
            ->where('utama', true)
            ->exists();

        $data = $request->validate([
            'kecamatan' => 'required|string',
            'kelurahan' => 'required|string',
            'detail_alamat' => 'required|string',
            'kode_pos' => 'required|string',
        ]);

        $alamat = new Alamat([
            ...$data,
            'provinsi' => 'DIY Yogyakarta',
            'id_pembeli' => $request->user()->id_pembeli,
            'utama' => !$sudahAdaUtama,
        ]);

        $alamat->save();


        return response()->json($alamat);
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
        $alamat = Alamat::findOrFail($id);
        $userId = $alamat->id_pembeli;
        $wasUtama = $alamat->utama;

        $alamat->delete();

        if ($wasUtama) {
            // Cari alamat tertua yang tersisa dan jadikan utama
            $alamatBaru = Alamat::where('id_pembeli', $userId)
                ->orderBy('created_at')
                ->first();

            if ($alamatBaru) {
                $alamatBaru->utama = true;
                $alamatBaru->save();
            }
        }

        return response()->json(['message' => 'Alamat berhasil dihapus']);
    }

    public function setUtama($id)
    {
        $alamat = Alamat::findOrFail($id);
        $userId = $alamat->id_pembeli;

        // Set semua alamat menjadi tidak utama
        Alamat::where('id_pembeli', $userId)->update(['utama' => false]);

        // Set yang dipilih jadi utama
        $alamat->utama = true;
        $alamat->save();

        return response()->json(['message' => 'Alamat berhasil dijadikan utama']);
    }

    public function getAlamatUtama($id)
    {
        $alamat = Alamat::where('id_pembeli', $id)
            ->where('utama', true)
            ->first();

        if (!$alamat) {
            return response()->json([
                'message' => 'Alamat utama tidak ditemukan'
            ], 404);
        }

        return response()->json($alamat);
    }
}
