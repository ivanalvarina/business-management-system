<?php

use Inertia\Testing\AssertableInertia as Assert;

test('inertia shares authenticated user permissions for navigation decisions', function () {
    $user = userWithPermissions(['dashboard.view', 'users.view']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.permissions', ['dashboard.view', 'users.view'])
        );
});
