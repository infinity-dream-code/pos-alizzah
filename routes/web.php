<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\Transaksi2Controller;
use App\Http\Controllers\DiskonController;
use App\Http\Controllers\WaitingBarangController;

Route::get('/', function () {
    return view('index');
});
Route::middleware(['auth', 'role:kasir'])->group(function () {

    Route::get('/kasir', [KasirController::class, 'index'])->name('index');

    Route::post('/kasir/checkout/tunai/process', [TransaksiController::class, 'processTunai'])
        ->name('transaksi.tunai');

    Route::post('/kasir/checkout/online/process', [TransaksiController::class, 'processOnline'])
        ->name('transaksi.online');
    Route::post('/kasir/inquiry-saldo', [TransaksiController::class, 'inquirySaldo'])
        ->name('kasir.inquiry.saldo');
    Route::post('/kasir/confirm-waiting', [TransaksiController::class, 'confirmWaitingBarang'])->name('kasir.confirm.waiting');
    Route::get('/kasir2', [Transaksi2Controller::class, 'index'])->name('kasir2.index');
    Route::post('/kasir2/checkout/tunai/process', [Transaksi2Controller::class, 'processTunai'])->name('kasir2.tunai.process');
    Route::post('/kasir2/checkout/online/process', [Transaksi2Controller::class, 'processOnline'])->name('kasir2.online.process');
    Route::post('/kasir2/inquiry-saldo', [Transaksi2Controller::class, 'inquirySaldo'])
        ->name('kasir2.inquiry.saldo');
});

Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['role:admin'])->group(function () {

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');

    Route::prefix('admin/barang')->group(function () {
        Route::get('/', [BarangController::class, 'index'])->name('barang.index');
        Route::get('/create', [BarangController::class, 'create'])->name('barang.create');
        Route::post('/store', [BarangController::class, 'store'])->name('barang.store');
        Route::get('/edit/{id}', [BarangController::class, 'edit'])->name('barang.edit');
        Route::put('/update/{id}', [BarangController::class, 'update'])->name('barang.update');
        Route::delete('/destroy/{id}', [BarangController::class, 'destroy'])->name('barang.destroy');
        Route::get('/cetak/{id}', [BarangController::class, 'cetak'])->name('barang.cetak');
    });

    Route::prefix('admin/laporan')->group(function () {
        Route::get('/', [TransaksiController::class, 'laporan'])->name('laporan.index');
        Route::get('/search', [TransaksiController::class, 'Search'])->name('laporan.search');
        Route::get('/cetak/pdf', [TransaksiController::class, 'cetakPdf'])->name('laporan.cetak.pdf');
    });


    Route::prefix('admin/user')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('user.index');
        Route::get('/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/store', [UserController::class, 'store'])->name('user.store');
        Route::get('/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::put('/update/{id}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/destroy/{id}', [UserController::class, 'destroy'])->name('user.destroy');
    });

    Route::prefix('admin/diskon')->group(function () {
        Route::get('/', [DiskonController::class, 'index']);
        Route::post('/store', [DiskonController::class, 'store']);
        Route::post('/update/{id}', [DiskonController::class, 'update']);
        Route::delete('/delete/{id}', [DiskonController::class, 'destroy']);
        Route::post('/toggle-status/{id}', [DiskonController::class, 'toggleStatus']);
        Route::post('/update-mass', [DiskonController::class, 'updateMass']);
        Route::post('/delete-mass', [DiskonController::class, 'deleteMass']);
        Route::get('/search-products', [DiskonController::class, 'searchProducts']);
    });

    Route::prefix('admin/pembelian')->group(function () {
        Route::get('/', [WaitingBarangController::class, 'index'])->name('waiting.index');
        Route::post('/store', [WaitingBarangController::class, 'store'])->name('waiting.store');
        Route::get('/search-products', [WaitingBarangController::class, 'searchProducts'])->name('waiting.search');
    });


    Route::prefix('admin/transaksi')->group(function () {
        Route::get('/', [TransaksiController::class, 'index'])->name('transaksi.index');
    });
});
