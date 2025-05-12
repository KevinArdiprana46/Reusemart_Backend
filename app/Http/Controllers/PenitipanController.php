<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Penitipan;
use Illuminate\Support\Facades\Auth;
class PenitipanController extends Controller
{
    public function showBarangPenitip()
    {
        $penitip = Auth::user();

        if (!$penitip || !$penitip->id_penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $penitipanList = Penitipan::with(['barang.foto_barang'])
            ->where('id_penitip', $penitip->id_penitip)
            ->get();

        return response()->json($penitipanList);
    }
}
