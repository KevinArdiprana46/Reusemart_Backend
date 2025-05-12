<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlamatController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DonasiController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\OrganisasiController;
use App\Http\Controllers\PembeliController;
use App\Http\Controllers\PenitipController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\UserController;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', action: [PembeliController::class, 'register']);

//ROute reset password
Route::post('/password/email', [ResetPasswordController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);

// Route::get('/user', function (Request $request) {
//     return $request->user();

// })->middleware('auth:sanctum');

Route::post('/register', [PembeliController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

//ROUTE PEMBELI
Route::prefix('pembeli')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [PembeliController::class, 'profile']);
    Route::post('/update', [PembeliController::class, 'update']);
    // Tambahkan route lain khusus pembeli di sini
});

//ROUTE ALAMAT
Route::prefix('alamat')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [AlamatController::class, 'index']);
    Route::post('/store', [AlamatController::class, 'store']);
    Route::get('/show/user', [AlamatController::class, 'show']);
    Route::put('/update/{id}', [AlamatController::class, 'update']);
    Route::delete('/destroy/{id}', [AlamatController::class, 'destroy']);
});


//ROUTE PEGAWAI CS
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/penitip', [PenitipController::class, 'index']);
    Route::post('/penitip/store', [PenitipController::class, 'store']);
    Route::get('/penitip/{id}', [PenitipController::class, 'show']);
    Route::put('/penitip/update/{id}', [PenitipController::class, 'update']);
    Route::delete('/penitip/delete/{id}', [PenitipController::class, 'destroy']);
    Route::get('/search/penitip', [PenitipController::class, 'search']);
});

//ROUTE ORGANISASI(REQUEST DONASI)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/donasi', [DonasiController::class, 'index']);
    Route::post('/donasi/store', [DonasiController::class, 'store']);
    Route::get('/donasi/{id}', [DonasiController::class, 'show']);
    Route::put('/donasi/update/{id}', [DonasiController::class, 'update']);
    Route::delete('/donasi/delete/{id}', [DonasiController::class, 'destroy']);
    Route::get('/search/donasi', [DonasiController::class, 'search']);
});



Route::post('/register', [PembeliController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

//ROUTE PEMBELI
Route::prefix('pembeli')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [PembeliController::class, 'profile']);
    Route::post('/update', [PembeliController::class, 'update']);
});

Route::prefix('alamat')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [AlamatController::class, 'index']);             
    Route::post('/store', [AlamatController::class, 'store']);           
    Route::get('/show/user', [AlamatController::class, 'show']);          
    Route::put('/update/{id}', [AlamatController::class, 'update']);        
    Route::delete('/destroy/{id}', [AlamatController::class, 'destroy']);    
});


//ROUTE PEGAWAI CS
Route::post('penitip/register', [PenitipController::class, 'register']);        
Route::prefix('penitip')->middleware('auth:sanctum')->group(function () {
    
    Route::get('/profile', [PenitipController::class, 'profile']);           
    Route::post('/update', [PenitipController::class, 'update']);           
    Route::delete('/{id}', [PenitipController::class, 'destroy']);         
    Route::get('/search', [PenitipController::class, 'search']);             
});

//ROUTE ORGANISASI(REQUEST DONASI)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/donasi', [DonasiController::class, 'index']);
    Route::post('/donasi', [DonasiController::class, 'store']);
    Route::get('/donasi/diminta', [DonasiController::class, 'getDonasiDiminta']);
    Route::get('/donasi/riwayat', [DonasiController::class, 'getRiwayatDonasi']);
    Route::get('/donasi/diterima', [DonasiController::class, 'getDonasiDiterima']);
    Route::get('/donasi/{id}', [DonasiController::class, 'show']);
    Route::put('/donasi/{id}', [DonasiController::class, 'update']);
    Route::delete('/donasi/{id}', [DonasiController::class, 'destroy']);
    Route::get('/search/donasi', [DonasiController::class, 'search']);
    Route::post('/donasi/kirim/{id}', [DonasiController::class, 'kirimDonasi']);
    Route::get('/barang/donasi', [BarangController::class, 'getBarangDonasi']);
});

//ROUTE CRUDS ORGANISASI
Route::prefix('organisasi')->group(function () {
    Route::post('/register', [OrganisasiController::class, 'store']); 
});

Route::middleware('auth:sanctum')->prefix('organisasi')->group(function () {
    Route::get('/', [OrganisasiController::class, 'index']);          
    Route::get('/show/{id}', [OrganisasiController::class, 'show']);      
    Route::put('/update/{id}', [OrganisasiController::class, 'update']);     
    Route::delete('/destroy/{id}', [OrganisasiController::class, 'destroy']); 
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/riwayat-pembelian', [TransaksiController::class, 'riwayatPembelian']);
    Route::get('/riwayat-penjualan', [TransaksiController::class, 'riwayatPenjualan']);
});

//ROUTE CRUDS PEGAWAI
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/pegawai', [PegawaiController::class, 'index']);
    Route::post('/pegawai/store', [PegawaiController::class, 'store']);
    Route::get('/pegawai/{id}', [PegawaiController::class, 'show']);
    Route::put('/pegawai/update/{id}', [PegawaiController::class, 'update']);
    Route::delete('/pegawai/delete/{id}', [PegawaiController::class, 'destroy']);
});
Route::middleware('auth:sanctum')->post('/admin/reset-password/pegawai', [AdminController::class, 'resetPasswordPegawai']);



Route::middleware('auth:sanctum')->group(function () {});
