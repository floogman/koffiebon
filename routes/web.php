<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/menu', [SiteController::class, 'menu'])->name('site.menu');
