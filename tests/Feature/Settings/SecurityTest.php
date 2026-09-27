<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertOk();
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee(__('Update password'))
        ->assertDontSee(__('Manage your passkeys for passwordless sign-in'))
        ->assertDontSee(__('Add a passkey to sign in without a password'))
        ->assertDontSee(__('Two-factor authentication'));
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});
