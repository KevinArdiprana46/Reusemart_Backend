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
    LaporanController,
    UserController,
    DiskusiController,
    KeranjangController,
    MerchandiseController,
};
use App\Models\Transaksi;

//FCM TOKEN
Route::middleware('auth:sanctum')->post('/update-fcm-token-pembeli', [PembeliController::class, 'updateFcmTokenPembeli']);
Route::middleware('auth:sanctum')->post('/update-fcm-token-penitip', [PenitipController::class, 'updateFcmTokenPenitip']);
Route::middleware('auth:sanctum')->post('/update-fcm-token-pegawai', [PegawaiController::class, 'updateFcmTokenPegawai']);


// 🔐 AUTH / REGISTER / LOGIN
Route::middleware('auth:sanctum')->get('/user', fn(Request $request) => $request->user());
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [PembeliController::class, 'register']);
Route::post('/password/email', [ResetPasswordController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [ResetPasswordController::class, 'reset']);

// 👤 PEMBELI
Route::get('/pembeli/{id}/alamat-utama', [AlamatController::class, 'getAlamatUtama']);
Route::prefix('pembeli')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [PembeliController::class, 'profile']);
    Route::post('/update', [PembeliController::class, 'update']);
    Route::get('/barang', [PembeliController::class, 'getAllBarang']);
});

// 🏠 ALAMAT
Route::prefix('alamat')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [AlamatController::class, 'index']);
    Route::post('/set-utama/{id}', [AlamatController::class, 'setUtama']);
    Route::post('/store', [AlamatController::class, 'store']);
    Route::get('/show/user', [AlamatController::class, 'show']);
    Route::put('/update/{id}', [AlamatController::class, 'update']);
    Route::delete('/destroy/{id}', [AlamatController::class, 'destroy']);
});

// 👤 PENITIP
Route::post('/penitip/register', [PenitipController::class, 'register']);
Route::prefix('penitip')->middleware('auth:sanctum')->group(function () {
    Route::get('/all', [PenitipController::class, 'getAllPenitip']);
    Route::get('/profile', [PenitipController::class, 'profile']);
    Route::post('/update', [PenitipController::class, 'update']);
    Route::get('/search', [PenitipController::class, 'search']);
    Route::get('/', [PenitipController::class, 'index']);
    Route::post('/store', [PenitipController::class, 'store']);
    Route::get('/{id}', [PenitipController::class, 'show']);
    Route::put('/update/{id}', [PenitipController::class, 'update']);
    Route::delete('/delete/{id}', [PenitipController::class, 'destroy']);
    Route::post('/generate-top-seller', [PenitipController::class, 'generateTopSeller']);
    Route::post('/penarikan-saldo', [PenitipController::class, 'tarikSaldo']);
});

// 🧾 DONASI
Route::prefix('donasi')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DonasiController::class, 'index']);
    Route::post('/', [DonasiController::class, 'store']);
    Route::get('/diminta', [DonasiController::class, 'getDonasiDiminta']);
    Route::get('/riwayat', [DonasiController::class, 'getRiwayatDonasi']);
    Route::get('/diterima', [DonasiController::class, 'getDonasiDiterima']);
    Route::get('/{id}', [DonasiController::class, 'show']);
    Route::put('/{id}', [DonasiController::class, 'update']);
    Route::delete('/{id}', [DonasiController::class, 'destroy']);
    Route::get('/search', [DonasiController::class, 'search']);
    Route::post('/kirim/{id}', [DonasiController::class, 'kirimDonasi']);
    Route::put('/update-donasi/{id}', [DonasiController::class, 'updateDonasi']);
});
Route::middleware('auth:sanctum')->get('/barang/donasi', [BarangController::class, 'getBarangDonasi']);

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

// 💬 DISKUSI (Chat Pembeli ↔ CS)
Route::prefix('diskusi')->middleware('auth:sanctum')->group(function () {
    Route::get('/{id_barang}', [DiskusiController::class, 'getByBarang']);
    Route::post('/kirim', [DiskusiController::class, 'kirimPesan']);
});

// 💰 TRANSAKSI
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/riwayat-pembelian', [TransaksiController::class, 'riwayatPembelian']);
    Route::get('/riwayat-penjualan', [TransaksiController::class, 'riwayatPenjualan']);
    Route::post('/checkout', [TransaksiController::class, 'checkout']);
    Route::post('/transaksi/upload-bukti/{id}', [TransaksiController::class, 'uploadBuktiPembayaran']);
    Route::get('/transaksi/dibayar', [TransaksiController::class, 'getTransaksiDibayar']);
    Route::post('/transaksi/verifikasi/{id}', [TransaksiController::class, 'verifikasiTransaksi']);
    Route::post('/transaksi/batalkan-otomatis', [TransaksiController::class, 'batalkanOtomatis']);
    Route::post('/transaksi/tolak/{id}', [TransaksiController::class, 'tolakTransaksi']);

    Route::post('/transaksi/konfirmasi-ambil/{id_penitipan}', [TransaksiController::class, 'konfirmasiAmbil']);
    Route::get('/gudang/transaksi', [TransaksiController::class, 'transaksiGudang']);
    Route::post('/gudang/transaksi/jadwalkan-kurir/{id_transaksi}', [TransaksiController::class, 'jadwalkanPengirimanKurir']);
    Route::post('/gudang/transaksi/jadwalkan-ambil-sendiri/{id_transaksi}', [TransaksiController::class, 'jadwalkanAmbilSendiri']);
    Route::post('/kurir/transaksi/kirim/{id_transaksi}', [TransaksiController::class, 'kirimBarang']);
    Route::post('/gudang/transaksi/konfirmasi-diterima/{id_transaksi}', [TransaksiController::class, 'konfirmasiBarangDiterima']);
    Route::post('/transaksi/hanguskan-otomatis', [TransaksiController::class, 'cekTransaksiHangus']);
    Route::post('/gudang/transaksi/hitung-komisi-hunter/{id_transaksi}', [TransaksiController::class, 'hitungKomisiHunterByTransaksi']);
    // Komisi
    Route::get('/komisi/reusemart', [TransaksiController::class, 'hitungKomisiReusemart']);
    Route::get('/komisi/penitip', [TransaksiController::class, 'hitungKomisiPenitip']);
    // Saldo
    Route::get('/saldo/penitip', [TransaksiController::class, 'tambahSaldoPenitip']);
    // Poin
    Route::get('/poin/pembeli', [TransaksiController::class, 'tambahPoinPembeli']);
    //Kirim barang oleh kurir
    Route::post('/kurir/transaksi/kirim/{id_transaksi}', [TransaksiController::class, 'kirimBarang']);
    Route::get('/kurir/transaksi', [TransaksiController::class, 'getTransaksiKurir']);
    Route::get('/kurir/transaksi/dikirim', [TransaksiController::class, 'pengirimanDikirim']);
    Route::post('/kurir/transaksi/diterima/{id}', [TransaksiController::class, 'diterimaOlehKurir']);
    Route::get('/kurir/transaksi/history', [TransaksiController::class, 'getHistoryKurir']);

    // Proses final satu transaksi (opsional efisien)
    Route::post('/transaksi/proses-final/{id}', [TransaksiController::class, 'prosesFinalTransaksi']);
    Route::get('/nota/{id_transaksi}/pdf', [TransaksiController::class, 'generateNotaPDF']);
    Route::get('/transaksi/semua', [TransaksiController::class, 'semuaTransaksi']);
    Route::get('/transaksi/laporan-komisi', [TransaksiController::class, 'laporanKomisiBulanan']);
    Route::get('/transaksi/valid', [TransaksiController::class, 'getRiwayatValid']);
    Route::post('/transaksi/{id}/batalkan', [TransaksiController::class, 'batalkanTransaksiPembeli']);
});

//Laporan
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/laporan/kategori-barang', [LaporanController::class, 'laporanPenjualanKategori']);
    Route::get('/laporan/barang-penitipan-habis', [LaporanController::class, 'barangPenitipanHabis']);
    Route::get('/laporan/penjualan-bulanan', [LaporanController::class, 'laporanPenjualanBulanan']);
    Route::get('/donasi/laporan/barang', [LaporanController::class, 'laporanDonasiBarang']);
    Route::get('/donasi/request/laporan', [LaporanController::class, 'laporanRequestDonasi']);
    Route::get('/laporan/transaksi-penitip/{id_penitip}/{bulan}/{tahun}', [LaporanController::class, 'laporanTransaksiPenitip']);
    Route::get('/laporan/kurir', [LaporanController::class, 'laporanKurir']);

});

// 👷‍♂️ PEGAWAI
Route::prefix('pegawai')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PegawaiController::class, 'index']);
    Route::get('/daftar', [PegawaiController::class, 'getDaftarPegawai']);
    Route::get('/hunter', [PegawaiController::class, 'getHunter']);
    Route::get('/hunter/komisi/daftar', [PegawaiController::class, 'daftarKomisi']);
    Route::get('/hunter/komisi', [PegawaiController::class, 'showDetailHunterWithKomisiHistory']);
    Route::get('/hunter/komisi/{id_transaksi}', [PegawaiController::class, 'getDetailKomisiHunter']);
    Route::get('/qc', [PegawaiController::class, 'getQc']);
    Route::post('/store', [PegawaiController::class, 'store']);
    Route::post('/update/{id}', [PegawaiController::class, 'update']);
    Route::get('/kurir', [PegawaiController::class, 'getListKurir']);
    Route::get('/{id}', [PegawaiController::class, 'show']);
    Route::delete('/delete/{id}', [PegawaiController::class, 'destroy']);
});
Route::middleware('auth:sanctum')->post('/admin/reset-password/pegawai', [AdminController::class, 'resetPasswordPegawai']);

// 📦 PENITIPAN
Route::prefix('penitipan')->middleware('auth:sanctum')->group(function () {
    Route::get('/barang', [PenitipanController::class, 'showBarangPenitip']);
    Route::get('/show-all', [PenitipanController::class, 'showAllPenitipan']);
    Route::get('/show-detail/{id}', [PenitipanController::class, 'showDetailPenitipan']);
    Route::post('/store', [PenitipanController::class, 'storePenitipanBarang']);
    Route::get('/show', [PenitipanController::class, 'index']);
    Route::put('/{id}', [PenitipanController::class, 'update']);
    Route::delete('/{id}', [PenitipanController::class, 'destroy']);
    Route::post('/barang/{id_penitipan}', [BarangController::class, 'storeBarangDalamPenitipan']);

    Route::get('/laporan/penitipan/habis', [PenitipanController::class, 'laporanBarangHabis']);
    Route::get('/barang/kategori/{kategori}', [PenitipanController::class, 'getBarangByKategori']);
    Route::get('/show/{id}', [PenitipanController::class, 'show']);
    Route::get('/search', [PenitipanController::class, 'searchBarangByNama']);
    Route::post('/perpanjang/{id}', [PenitipanController::class, 'perpanjangPenitipan']);
    Route::post('/gudang/penitipan/ambil/{id}', [PenitipanController::class, 'konfirmasiPengambilan']);
    Route::post('/ambil-kembali/{id_barang}', [PenitipanController::class, 'konfirmasiPengambilanKembali']);

    Route::post('/full-store', [PenitipanController::class, 'storePenitipan']);

    Route::get('/test-notifikasi-penitip/{id_penitip}', [PenitipanController::class, 'testKirimNotifikasi']);
    Route::get('/test-notif-penitipan', [PenitipanController::class, 'testNotifikasiTanggal']);
    Route::get('/get-penitipan-baru', [PenitipanController::class, 'getPenitipanTertentu']);
    Route::get('/riwayat-penitipan', [PenitipanController::class, 'riwayatPenitipan']);

    Route::post('/barang/{id}/donasi-sukarela', [BarangController::class, 'sumbangBarangSukarela']);
});

// 📦 BARANG
Route::get('/barang/rekomendasi', [BarangController::class, 'getBarangRekomendasi']);
Route::get('/kategori/{kategori}', [BarangController::class, 'getByKategori']);
Route::get('/barang/search', [BarangController::class, 'index']);
Route::get('/detail-barang/{id}', [BarangController::class, 'getDetailBarang']);
Route::middleware('auth:sanctum')->prefix('barang')->group(function () {
    Route::get('/', [BarangController::class, 'index']);
    Route::get('/kategori/{kategori}', [BarangController::class, 'getByKategori']);
    Route::post('/', [BarangController::class, 'store']);
    Route::post('/update/{id}', [BarangController::class, 'update']);
    Route::post('/upload-foto/{id}', [BarangController::class, 'uploadFotoBarang']);
    Route::get('/all', [BarangController::class, 'getAllBarangForPegawai']);
    // Route::get('/{id}', [BarangController::class, 'show']);
    Route::post('/upload-foto/{id}', [BarangController::class, 'uploadFotoBarang']);
    Route::get('/terjual', [BarangController::class, 'getBarangTerjual']);
    Route::put('/rating/{id}', [BarangController::class, 'beriRatingBarang']);
    Route::get('/hitung-rating/{id}', [BarangController::class, 'hitungRatingPenitip']);
    // Route::post('/foto-barang/upload', [BarangController::class, 'uploadFotoBarang']);
    Route::get('/detail-barang/{id}', [BarangController::class, 'getDetailBarang']);
    Route::get('/laporan/stok-gudang', [BarangController::class, 'stokGudang']);
    Route::get('/generate-konversi-donasi', [BarangController::class, 'konversiDonasi']);
});

//NonLogin
Route::get('barang/{id}', [BarangController::class, 'show']);
Route::get('/barang/kategori/{kategori}', [BarangController::class, 'getByKategori'])->where('kategori', '.*');
Route::get('non/all', [BarangController::class, 'getAllNonBarangForPegawai']);
Route::get('non/kategori/{kategori}', [BarangController::class, 'getNonByKategori'])->where('kategori', '.*');
Route::get('non/{id}', [BarangController::class, 'showNon']);
Route::get('non/', [BarangController::class, 'index']);

// DISKUSI
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/diskusi/{id_barang}', [DiskusiController::class, 'getByBarang']);
    Route::post('/diskusi/kirim', [DiskusiController::class, 'kirimPesan']);
    Route::post('/diskusi/baca/{id_barang}', [DiskusiController::class, 'tandaiDiskusiSudahDibaca']);
});

//Keranjang
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/keranjang/tambah', [KeranjangController::class, 'tambah']);
    Route::delete('/keranjang/hapus/{id}', [KeranjangController::class, 'hapus']);
    Route::get('/keranjang', [KeranjangController::class, 'index']);
    Route::get('/keranjang/count', [KeranjangController::class, 'getCount']);
});

//Merchandise
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/merchandise/tambah', [MerchandiseController::class, 'store']);
    Route::get('/merchandise', [MerchandiseController::class, 'index']);
    Route::post('/merchandise/{id}/upload-foto', [MerchandiseController::class, 'uploadFotoMerchandise']);
    Route::get('/merchandise/klaim', [MerchandiseController::class, 'listKlaim']);
    Route::post('/merchandise/klaim', [MerchandiseController::class, 'klaimMerchandise']);
    Route::put('/merchandise/klaim/tanggal-ambil/{id}', [MerchandiseController::class, 'isiTanggalAmbil']);
});
