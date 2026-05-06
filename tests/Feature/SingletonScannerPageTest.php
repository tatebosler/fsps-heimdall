<?php

use App\Http\Middleware\AdminToolsAuth;

test('singleton scanner page requires admin tools auth', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->get('/admin/singleton')
        ->assertRedirect(route('admin.login'));
});

test('singleton scanner page renders live scan controls', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->get('/admin/singleton')
        ->assertSuccessful()
        ->assertSee('Nadamoo Live Scanner')
        ->assertSee('x-data="{ showControlCodes: true }"', false)
        ->assertSee('Scan Input')
        ->assertSee('Recent scans')
        ->assertSee('data-singleton-scanner-root', false)
        ->assertSee('data-singleton-input', false)
        ->assertSee('data-singleton-log', false);
});
