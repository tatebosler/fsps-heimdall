<?php

use App\Http\Middleware\AdminToolsAuth;

test('guests are redirected to the admin login page', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->get('/admin/historical')
        ->assertRedirect(route('admin.login'));
});

test('admin login page shows the shared password prompt with autocomplete disabled', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Enter the coordinator tools password to proceed')
        ->assertSee('autocomplete="off"', false)
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('name="potato12345"', false)
        ->assertSee('dark:bg-gray-900', false)
        ->assertSee('dark:bg-gray-800', false);
});

test('correct shared password unlocks admin tools', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->post(route('admin.login.attempt'), [
        'potato12345' => 'shared-secret',
    ])->assertRedirect('/admin/wb');

    $this->get('/admin/wb')
        ->assertOk()
        ->assertSee('Lock tools');
});

test('locking tools removes access to protected admin routes', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->get('/admin/wb')
        ->assertRedirect(route('admin.login'));
});
