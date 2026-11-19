<?php

use Illuminate\Support\Facades\Route;

Route::get('/catalog', fn () => 'catalog')->name('catalog.index');
