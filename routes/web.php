<?php

use App\Http\Controllers\CatalogPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogPageController::class, 'index'])->name('catalog.index');
