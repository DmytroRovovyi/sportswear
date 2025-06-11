<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CatalogFilters;
use Illuminate\Support\Facades\Route;

Route::get('/catalog/products', [CatalogController::class, 'products']);
Route::get('/catalog/filters', [CatalogFilters::class, 'filters']);
