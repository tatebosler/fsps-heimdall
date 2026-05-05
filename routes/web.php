<?php

use App\Http\Middleware\AdminToolsAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::redirect('/tos', '/terms');
Route::livewire('/estimates', 'estimates')->name('estimates');
Route::livewire('/notifications', 'notification-signup')->name('notifications');
Route::livewire('/notifications/phone', 'notification-signup')->name('notifications.phone');

Route::prefix('admin')->group(function () {
    Route::get('/login', function (): RedirectResponse|View {
        if (session(AdminToolsAuth::SESSION_KEY, false)) {
            return redirect()->to('/admin/wb');
        }

        return view('admin.login');
    })->name('admin.login');

    Route::post('/login', function (Request $request): RedirectResponse {
        $validated = $request->validate([
            'potato12345' => ['required', 'string'],
        ]);

        $configuredPassword = config('ps.admin_tools_password');

        if (! is_string($configuredPassword) || $configuredPassword === '') {
            return back()->withErrors([
                'potato12345' => 'The coordinator tools password is not configured.',
            ]);
        }

        if (! hash_equals($configuredPassword, $validated['potato12345'])) {
            return back()->withErrors([
                'potato12345' => 'The provided password is incorrect.',
            ]);
        }

        $request->session()->put(AdminToolsAuth::SESSION_KEY, true);
        $request->session()->regenerate();

        return redirect()->intended('/admin/wb');
    })->middleware('throttle:6,1')->name('admin.login.attempt');

    Route::post('/logout', function (Request $request): RedirectResponse {
        $request->session()->forget(AdminToolsAuth::SESSION_KEY);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->middleware('admin.tools')->name('admin.logout');

    Route::middleware('admin.tools')->group(function () {
        Route::livewire('/historical', 'historical-data-viewer')->name('historical');
        Route::livewire('/entry-io', 'entry-data-io')->name('entry-data-io');
        Route::livewire('/editor', 'data-editor')->name('data-editor');
        Route::livewire('/tower', 'tower')->name('tower');
        Route::livewire('/wb', 'wristband-booth')->name('wristband-booth');
    });
});
