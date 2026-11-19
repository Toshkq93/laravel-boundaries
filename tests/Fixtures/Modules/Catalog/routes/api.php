<?php

use Illuminate\Support\Facades\Route;

Route::get('/catalog/products', fn () => ['data' => []])->name('api.catalog.products');
