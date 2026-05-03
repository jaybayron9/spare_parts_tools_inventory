<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'inventory.index')->name('inventory.index');
Route::view('/notifications', 'inventory.notifications')->name('inventory.notifications');
