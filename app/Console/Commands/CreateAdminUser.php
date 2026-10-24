<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create a general manager account (no center) for the control panel';

    public function handle(): int
    {
        $name = text(label: 'Full name', required: true);

        $username = text(
            label: 'Username',
            required: true,
            validate: fn (string $value) => User::where('username', $value)->exists()
                ? 'This username is already taken.'
                : null,
        );

        $email = text(
            label: 'Email (optional once username login works)',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Invalid email address.',
                User::where('email', $value)->exists() => 'This email is already used.',
                default => null,
            },
        );

        $password = password(
            label: 'Password (at least 10 characters)',
            required: true,
            validate: fn (string $value) => mb_strlen($value) < 10
                ? 'The password is too short.'
                : null,
        );

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'center_id' => null,
            'is_active' => true,
        ]);

        Role::findOrCreate('general_manager', 'web');
        $user->assignRole('general_manager');

        $this->info('General manager account created.');

        return self::SUCCESS;
    }
}
