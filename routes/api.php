<?php

use App\Http\Controllers\AlamatController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\OrganisasiController;
use App\Http\Controllers\PembeliController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [PembeliController::class, 'register']);

//ROute reset password
Route::post('/password/email', [ResetPasswordController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);


//ROUTE CRUDS ALAMAT
Route::get('/alamat', [AlamatController::class, 'index']);
Route::post('/alamat/store', [AlamatController::class, 'store']);
Route::get('/alamat/{id}', [AlamatController::class, 'show']);
Route::put('/alamat/update/{id}', [AlamatController::class, 'update']);
Route::delete('/alamat/delete/{id}', [AlamatController::class, 'destroy']);

//ROUTE CRUDS ORGANISASI
Route::get('/organisasi', [OrganisasiController::class, 'index']);
Route::post('/organisasi/store', [OrganisasiController::class, 'store']);
Route::get('/organisasi/{id}', [OrganisasiController::class, 'show']);
Route::put('/organisasi/update/{id}', [OrganisasiController::class, 'update']);
Route::delete('/organisasi/delete/{id}', [OrganisasiController::class, 'destroy']);



Route::middleware('auth:sanctum')->group(function () {

});

