<?php

use App\Models\User;

test('the login screen can be rendered', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Đăng nhập')
        ->assertSee('Ghi nhớ đăng nhập');
});

test('an active user can log in with valid credentials', function () {
    $this->freezeSecond();
    $user = User::factory()->create(['last_login_at' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at->equalTo(now()))->toBeTrue();
});

test('a logged in user is redirected to the page they originally requested', function () {
    $user = User::factory()->create();

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('ticking remember me stores a remember token', function () {
    $user = User::factory()->create(['remember_token' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('a user cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.'])
        ->assertSessionHasInput('email', $user->email);

    $this->assertGuest();
});

test('a deactivated user cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.']);

    $this->assertGuest();
});

test('email and password are required', function () {
    $this->post(route('login.store'), [])
        ->assertSessionHasErrors([
            'email' => 'Vui lòng nhập email.',
            'password' => 'Vui lòng nhập mật khẩu.',
        ]);

    $this->assertGuest();
});

test('the email must be a valid address', function () {
    $this->post(route('login.store'), [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => 'Email không hợp lệ.']);
});

test('login is locked after five failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session('errors')->first('email'))->toStartWith('Bạn đã thử quá nhiều lần.');
});

test('an authenticated user is redirected away from the login screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('a user can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
