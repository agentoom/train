<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('agentoom-train:create-user')]
#[Description('Create a new user by providing name, email and password interactively')]
class CreateUserCommand extends Command
{
    public function handle(): int
    {
        $name = $this->ask('Name');

        $email = $this->ask('Email');

        $emailValidator = Validator::make(['email' => $email], ['email' => 'required|email|unique:users,email']);

        if ($emailValidator->fails()) {
            $this->error('Invalid or already taken email: '.$emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->secret('Password');

        $passwordValidator = Validator::make(['password' => $password], ['password' => 'required|min:8']);

        if ($passwordValidator->fails()) {
            $this->error($passwordValidator->errors()->first('password'));

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("User \"{$name}\" created successfully.");

        return self::SUCCESS;
    }
}
