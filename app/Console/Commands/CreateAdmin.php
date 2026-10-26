<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the initial Super Administrator. No default/public password exists:
 * the password is typed (hidden) or generated randomly and shown once.
 */
class CreateAdmin extends Command
{
    protected $signature = 'autoshop:create-admin {--name=} {--email=} {--generate-password : Generate a strong random password}';

    protected $description = 'Create a Super Administrator account securely';

    public function handle(): int
    {
        $this->call('autoshop:sync-permissions');

        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $generated = $this->option('generate-password') || ! $this->input->isInteractive();

        if ($generated) {
            $password = Str::password(20);
        } else {
            $password = $this->secret('Password (min 12 chars, mixed case, numbers, symbols)');
            if ($password !== $this->secret('Confirm password')) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        $v = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'status' => 'approved']);
        $user->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now(), 'approved_at' => now()])->save();
        $user->assignRole('Super Administrator');

        $this->info("Super Administrator {$email} created.");
        if ($generated) {
            $this->warn('Generated password (shown only once — store it securely and change it after first login):');
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
