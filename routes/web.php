<?php

use App\Http\Controllers\MailPreviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inventory.index')->name('inventory.index');
Route::view('/notifications', 'inventory.notifications')->name('inventory.notifications');
Route::get('/mail-preview', MailPreviewController::class)->name('mail.preview');
