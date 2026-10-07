<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {--name=Admin : Admin display name}
                            {--email= : Admin email}
                            {--password= : Admin password}';

    protected $description = 'Create or promote a TailorMate admin user (no shop)';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: 'Admin');
        $email = (string) ($this->option('email') ?: env('ADMIN_EMAIL', ''));
        $password = (string) ($this->option('password') ?: env('ADMIN_PASSWORD', ''));

        if ($email === '') {
            $email = (string) $this->ask('Admin email');
        }

        if ($password === '') {
            $password = (string) $this->secret('Admin password');
        }

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->update([
                'name' => $name,
                'password' => $password,
                'role' => UserRole::Admin,
            ]);
            $this->info("Updated existing user to admin: {$email}");
        } else {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
            ]);
            $this->info("Created admin: {$email}");
        }

        return self::SUCCESS;
    }
}
