<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::redirect('/tos', '/terms');
Route::livewire('/notifications', 'notification-signup')->name('notifications');
