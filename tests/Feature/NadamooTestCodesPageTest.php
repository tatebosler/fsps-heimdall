<?php

use App\Http\Middleware\AdminToolsAuth;

test('nadamoo test codes page requires admin tools auth', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->get('/admin/ntcodes')
        ->assertRedirect(route('admin.login'));
});

test('nadamoo test codes page renders grouped labels and hides raw payloads', function () {
    config()->set('ps.admin_tools_password', 'shared-secret');

    $this->withSession([
        AdminToolsAuth::SESSION_KEY => true,
    ])->get('/admin/ntcodes')
        ->assertSuccessful()
        ->assertSee('Nadamoo &amp; Test Codes', false)
        ->assertSee('Test Codes')
        ->assertSee('Nadamoo Reset Codes')
        ->assertSee('Nadamoo Bluetooth')
        ->assertSee('Nadamoo Storage Mode')
        ->assertSee('OK')
        ->assertSee('Group Zero')
        ->assertDontSee('%%SpecCode93')
        ->assertDontSee('^#SC^303FFF0')
        ->assertDontSee('%%SpecCodeAA');
});
