<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('first login with a temporary password requires a password change', function () {
    $user = User::factory()->create([
        'password' => 'temporary',
        'must_change_password' => true,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'temporary',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))
        ->assertRedirect(route('password.change.edit'));
});

test('users with a temporary password are redirected to the required password change page', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.edit'));

    $this->actingAs($user)
        ->get(route('password.change.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ChangePassword'),
        );
});

test('users can replace their temporary password once', function () {
    $user = User::factory()->create([
        'password' => 'temporary',
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('abcdefgh', $user->password))->toBeTrue();
});

test('replacement password must be at least eight characters', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'short7',
            'password_confirmation' => 'short7',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('replacement password must differ from the temporary password', function () {
    $user = User::factory()->create([
        'password' => 'temporary',
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'temporary',
            'password_confirmation' => 'temporary',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('users who completed the required change cannot submit it again', function () {
    $user = User::factory()->create(['must_change_password' => false]);

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])
        ->assertForbidden();
});
