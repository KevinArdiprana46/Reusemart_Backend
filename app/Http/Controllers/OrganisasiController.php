<?php

namespace App\Http\Controllers;

use App\Models\Organisasi;
use Illuminate\Http\Request;

class OrganisasiController extends Controller
{
    // tampil semua
    public function index()
    {
        return Organisasi::all();
    }

    // create organisasi
    public function store(Request $request)
    {
        $request->validate([
            'nama_organisasi' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'alamat' => 'required|string',
            'nama_penerima' => 'required|string|max:255',
        ]);


        $organisasi = Organisasi::create([
            'nama_organisasi' => $request->nama_organisasi,
            'no_telepon' => $request->no_telepon,
            'alamat' => $request->alamat,
            'nama_penerima' => $request->nama_penerima,
            'id_role' => 4,
        ]);

        return response()->json([
            'message' => 'Organisasi berhasil ditambahkan',
            'data' => $organisasi
        ], 201);
    }

    // show by id
    public function show($id)
    {
        $organisasi = Organisasi::findOrFail($id);

        return response()->json($organisasi);
    }

    // update organisasi
    public function update(Request $request, $id)
    {
        $organisasi = Organisasi::findOrFail($id);

        $request->validate([
            'nama_organisasi' => 'sometimes|required|string|max:255',
            'no_telepon' => 'sometimes|required|string|max:20',
            'alamat' => 'sometimes|required|string',
            'nama_penerima' => 'sometimes|required|string|max:255',
        ]);

        $organisasi->update($request->all());

        return response()->json([
            'message' => 'Organisasi berhasil diperbarui',
            'data' => $organisasi
        ]);
    }


    // Hapus organisasi
    public function destroy($id)
    {
        $organisasi = Organisasi::findOrFail($id);
        $organisasi->delete();

        return response()->json(['message' => 'Organisasi berhasil dihapus']);
    }
}
