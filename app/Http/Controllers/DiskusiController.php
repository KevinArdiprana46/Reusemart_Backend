<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Diskusi;
use App\Models\Pegawai;
use App\Models\Pembeli;

class DiskusiController extends Controller
{
    // Ambil semua diskusi berdasarkan barang
    public function getByBarang($id_barang)
    {
        $diskusi = Diskusi::where('id_barang', $id_barang)
            ->with([
                'pembeli:id_pembeli,nama_lengkap',
                'pegawai:id_pegawai,nama_lengkap'
            ])
            ->orderBy('id_diskusi', 'asc')
            ->get();

        return response()->json($diskusi);
    }


    // Kirim pesan diskusi
    public function kirimPesan(Request $request)
    {
        $request->validate([
            'id_barang' => 'required|exists:barang,id_barang',
            'pesan_diskusi' => 'required|string',
        ]);

        $role = $request->header('Role'); // Ambil role dari header
        $user = auth()->user();

        $diskusi = new Diskusi();
        $diskusi->id_barang = $request->id_barang;
        $diskusi->pesan_diskusi = $request->pesan_diskusi;

        if ($role === 'pembeli' && isset($user->id_pembeli)) {
            $diskusi->id_pembeli = $user->id_pembeli;
        } elseif ($role === 'pegawai' && isset($user->id_pegawai)) {
            $pegawai = Pegawai::with('jabatan')->find($user->id_pegawai);
            if (!$pegawai || $pegawai->id_jabatan != 3) {
                return response()->json(['message' => 'Hanya pegawai dengan jabatan CS (id_jabatan = 3) yang boleh kirim pesan.'], 403);
            }
            $diskusi->id_pegawai = $user->id_pegawai;
        } else {
            return response()->json(['message' => 'User tidak valid untuk diskusi.'], 403);
        }

        $diskusi->save();

        return response()->json(['message' => 'Pesan berhasil dikirim!', 'data' => $diskusi]);
    }

}

