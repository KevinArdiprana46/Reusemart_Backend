<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pembeli;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PembeliController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_lengkap'   => 'required|string|max:255',
            'gender'         => 'required|in:Laki-laki,Perempuan',
            'email'          => 'required|email|unique:pembeli,email',
            'no_telepon'     => 'required|string|max:15',
            'password'       => 'required|string|min:6',
            'tanggal_lahir'  => 'required|date',
            'poin_sosial'    => 'nullable|integer|min:0',
            'image_user'     => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pembeli = Pembeli::create([
            'nama_lengkap'   => $request->nama_lengkap,
            'gender'         => $request->gender,
            'email'          => $request->email,
            'no_telepon'     => $request->no_telepon,
            'password'       => Hash::make($request->password),
            'tanggal_lahir'  => $request->tanggal_lahir,
            'poin_sosial'    => $request->poin_sosial ?? 0, 
            'image_user'     => $request->image_user ?? 'default.jpg',
            'id_role'   => 2,
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil',
            'data' => $pembeli
        ], 201);
    }
}
