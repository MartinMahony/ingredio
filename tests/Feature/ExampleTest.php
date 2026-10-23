<?php

test('the application redirects guests to login', function () {
    $this->get('/')->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('the deployment health check is publicly accessible', function () {
    $this->get('/up')
        ->assertOk()
        ->assertSee('Application up');
});
