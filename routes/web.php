<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::redirect('/tos', '/terms');
Route::livewire('/estimates', 'estimates')->name('estimates');
Route::livewire('/notifications', 'notification-signup')->name('notifications');
Route::livewire('/wb', 'wristband-booth')->name('wristband-booth');
