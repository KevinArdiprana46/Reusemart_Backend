<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keranjang;
use Illuminate\Support\Facades\Log;

class KeranjangController extends Controller
{

    public function tambah(Request $request)
    {
        $request->validate([
            'id_barang' => 'required|exists:barang,id_barang',
        ]);

        $id_pembeli = auth()->user()->id_pembeli;

        // Cari apakah barang sudah ada di keranjang
        $keranjang = Keranjang::where('id_pembeli', $id_pembeli)
            ->where('id_barang', $request->id_barang)
            ->first();

        if ($keranjang) {
            $keranjang->jumlah += 1;
            $keranjang->save();
        } else {
            $keranjang = Keranjang::create([
                'id_pembeli' => $id_pembeli,
                'id_barang' => $request->id_barang,
                'jumlah' => 1,
            ]);
        }

        $keranjang = Keranjang::with('barang')->find($keranjang->id);

        return response()->json([
            'message' => 'Barang ditambahkan ke keranjang',
            'data' => $keranjang
        ]);
    }


    public function hapus($id)
    {
        $id_pembeli = auth()->user()->id_pembeli;

        $keranjang = Keranjang::where('id', $id)
            ->where('id_pembeli', $id_pembeli)
            ->first();

        if (!$keranjang) {
            return response()->json(['message' => 'Item keranjang tidak ditemukan'], 404);
        }

        $keranjang->delete();

        return response()->json(['message' => 'Barang dihapus dari keranjang']);
    }

    public function index()
    {
        $id_pembeli = auth()->user()->id_pembeli;
        $items = Keranjang::with('barang')->where('id_pembeli', $id_pembeli)->get();
        return response()->json($items);
    }

    public function getCount()
    {
        $id_pembeli = auth()->user()->id_pembeli;
        $count = Keranjang::where('id_pembeli', $id_pembeli)->sum('jumlah');
        return response()->json(['count' => $count]);
    }

}
