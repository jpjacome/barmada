<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin {email} {password} {name?}';
    protected $description = 'Create a new admin user';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $name = $this->argument('name') ?? 'Admin User';

        $user = User::create([
            'username' => strtolower(preg_replace('/\s+/', '', $name)) . rand(1000, 9999),
            'first_name' => $name,
            'last_name' => 'Admin',
            'name' => $name . ' Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info('Admin user created successfully!');
        $this->info('Name: ' . $name);
        $this->info('Email: ' . $email);
    }
}