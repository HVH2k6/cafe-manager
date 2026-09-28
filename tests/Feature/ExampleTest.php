<?php

test('the home page sends guests to the login screen', function () {
    $this->get('/')->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertRedirect(route('login'));
});
