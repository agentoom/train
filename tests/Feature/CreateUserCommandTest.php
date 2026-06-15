<?php

use App\Models\User;

test('create-user command creates a user with valid inputs', function () {
    $this->artisan('agentoom-train:create-user')
        ->expectsQuestion('Name', 'John Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'secret1234')
        ->expectsOutput('User "John Doe" created successfully.')
        ->assertExitCode(0);

    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

test('create-user command fails when email is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('agentoom-train:create-user')
        ->expectsQuestion('Name', 'Another User')
        ->expectsQuestion('Email', 'taken@example.com')
        ->assertExitCode(1);
});

test('create-user command fails when email is invalid', function () {
    $this->artisan('agentoom-train:create-user')
        ->expectsQuestion('Name', 'Bad Email User')
        ->expectsQuestion('Email', 'not-an-email')
        ->assertExitCode(1);
});

test('create-user command fails when password is too short', function () {
    $this->artisan('agentoom-train:create-user')
        ->expectsQuestion('Name', 'Short Pass User')
        ->expectsQuestion('Email', 'shortpass@example.com')
        ->expectsQuestion('Password', 'abc')
        ->assertExitCode(1);

    expect(User::where('email', 'shortpass@example.com')->exists())->toBeFalse();
});
