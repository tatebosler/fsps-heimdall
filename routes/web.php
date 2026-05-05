<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::redirect('/tos', '/terms');
Route::livewire('/estimates', 'estimates')->name('estimates');
Route::livewire('/notifications', 'notification-signup')->name('notifications');
Route::livewire('/notifications/phone', 'notification-signup')->name('notifications.phone');

Route::group(['prefix' => 'admin'], function () {
    Route::livewire('/historical', 'historical-data-viewer')->name('historical');
    Route::livewire('/entry-io', 'entry-data-io')->name('entry-data-io');
    Route::livewire('/editor', 'data-editor')->name('data-editor');
    Route::livewire('/tower', 'tower')->name('tower');
    Route::livewire('/wb', 'wristband-booth')->name('wristband-booth');
});
