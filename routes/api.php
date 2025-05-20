<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    AdminController,
    AlamatController,
    BarangController,
    DonasiController,
    LoginController,
    OrganisasiController,
    PegawaiController,
    PembeliController,
    PenitipController,
    PenitipanController,
    ResetPasswordController,
    TransaksiController,
    UserController
};

// 🔐 AUTH / REGISTER / LOGIN
Route::middleware('auth:sanctum')->get('/user', fn(Request $request) => $request->user());
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [PembeliController::class, 'register']);
Route::post('/password/email', [ResetPasswordController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);

// 📦 PEMBELI
Route::prefix('pembeli')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [PembeliController::class, 'profile']);
    Route::post('/update', [PembeliController::class, 'update']);
    Route::get('/barang', [PembeliController::class, 'getAllBarang']);
});

// 📍 ALAMAT
Route::prefix('alamat')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [AlamatController::class, 'index']);
    Route::post('/store', [AlamatController::class, 'store']);
    Route::get('/show/user', [AlamatController::class, 'show']);
    Route::put('/update/{id}', [AlamatController::class, 'update']);
    Route::delete('/destroy/{id}', [AlamatController::class, 'destroy']);
});

// 👨‍💼 PENITIP
Route::post('/penitip/register', [PenitipController::class, 'register']);
Route::prefix('penitip')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [PenitipController::class, 'profile']);
    Route::post('/update', [PenitipController::class, 'update']);
    Route::delete('/{id}', [PenitipController::class, 'destroy']);
    Route::get('/search', [PenitipController::class, 'search']);

    // Untuk CS mengelola penitip
    Route::get('/', [PenitipController::class, 'index']);
    Route::post('/store', [PenitipController::class, 'store']);
    Route::get('/{id}', [PenitipController::class, 'show']);
    Route::put('/update/{id}', [PenitipController::class, 'update']);
    Route::delete('/delete/{id}', [PenitipController::class, 'destroy']);
});

// 🏢 ORGANISASI
Route::prefix('organisasi')->group(function () {
    Route::post('/register', [OrganisasiController::class, 'store']);
});
Route::prefix('organisasi')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OrganisasiController::class, 'index']);
    Route::get('/show/{id}', [OrganisasiController::class, 'show']);
    Route::put('/update/{id}', [OrganisasiController::class, 'update']);
    Route::delete('/destroy/{id}', [OrganisasiController::class, 'destroy']);
});

// 🧾 DONASI
Route::prefix('donasi')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DonasiController::class, 'index']);
    Route::post('/', [DonasiController::class, 'store']);
    Route::get('/{id}', [DonasiController::class, 'show']);
    Route::put('/{id}', [DonasiController::class, 'update']);
    Route::delete('/{id}', [DonasiController::class, 'destroy']);
    Route::get('/diminta', [DonasiController::class, 'getDonasiDiminta']);
    Route::get('/diterima', [DonasiController::class, 'getDonasiDiterima']);
    Route::get('/riwayat', [DonasiController::class, 'getRiwayatDonasi']);
    Route::post('/kirim/{id}', [DonasiController::class, 'kirimDonasi']);
    Route::get('/search', [DonasiController::class, 'search']);
});

// Tambahan: barang untuk donasi
Route::middleware('auth:sanctum')->get('/barang/donasi', [BarangController::class, 'getBarangDonasi']);

// 🧾 TRANSAKSI
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/riwayat-pembelian', [TransaksiController::class, 'riwayatPembelian']);
    Route::get('/riwayat-penjualan', [TransaksiController::class, 'riwayatPenjualan']);
    Route::post('/transaksi/konfirmasi-ambil/{id_penitipan}', [TransaksiController::class, 'konfirmasiAmbil']);
    Route::get('/gudang/transaksi', [TransaksiController::class, 'transaksiGudang']);
    Route::post('/gudang/transaksi/jadwalkan-kurir/{id_transaksi}', [TransaksiController::class, 'jadwalkanPengirimanKurir']);
    Route::post('/gudang/transaksi/jadwalkan-ambil-sendiri/{id_transaksi}', [TransaksiController::class, 'jadwalkanAmbilSendiri']);
    Route::post('/gudang/transaksi/konfirmasi-diterima/{id_transaksi}', [TransaksiController::class, 'konfirmasiBarangDiterima']);
    Route::post('/transaksi/hanguskan-otomatis', [TransaksiController::class, 'cekTransaksiHangus']);

});

// 👷‍♂ PEGAWAI
Route::prefix('pegawai')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PegawaiController::class, 'index']);
    Route::post('/store', [PegawaiController::class, 'store']);
    Route::get('/{id}', [PegawaiController::class, 'show']);
    Route::put('/update/{id}', [PegawaiController::class, 'update']);
    Route::delete('/delete/{id}', [PegawaiController::class, 'destroy']);
});

// 🛠 RESET PASSWORD PEGAWAI
Route::middleware('auth:sanctum')->post('/admin/reset-password/pegawai', [AdminController::class, 'resetPasswordPegawai']);

// 📦 PENITIPAN (fitur tambahan penitip)
Route::middleware('auth:sanctum')->prefix('penitipan')->group(function () {
    Route::get('/barang', [PenitipanController::class, 'showBarangPenitip']);
    Route::get('/barang/kategori/{kategori}', [PenitipanController::class, 'getBarangByKategori'])->where('kategori', '.*');
    Route::get('/show/{id}', [PenitipanController::class, 'show']);
    Route::get('/search', [PenitipanController::class, 'searchBarangByNama']);
    Route::post('/perpanjang/{id}', [PenitipanController::class, 'perpanjangPenitipan']);
    Route::post('/gudang/penitipan/ambil/{id}', [PenitipanController::class, 'konfirmasiPengambilan']);

});

//ROUTE BARANG
Route::middleware('auth:sanctum')->prefix('barang')->group(function () {
    Route::get('/all', [BarangController::class, 'getAllBarangForPegawai']);
    Route::get('/kategori/{kategori}', [BarangController::class, 'getByKategori'])->where('kategori', '.*');
    Route::get('/{id}', [BarangController::class, 'show']);
    Route::get('/', [BarangController::class, 'index']);
});

// // DISKUSI
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/diskusi/{id_barang}', [DiskusiController::class, 'getByBarang']);
//     Route::post('/diskusi/kirim', [DiskusiController::class, 'kirimPesan']);
// });