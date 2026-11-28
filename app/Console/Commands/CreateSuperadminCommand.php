<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperadminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'esagip:create-superadmin
                            {--name= : Full name of the superadmin}
                            {--email= : Email address of the superadmin}
                            {--password= : Password (min 8 characters, letters and numbers)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Superadmin account for the E-Sagip admin portal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password');

        if (! $password) {
            $password = $this->secret('Password (min 8 chars, letters and numbers)');
            $confirmation = $this->secret('Confirm password');

            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $superadmin = User::create([
            'name' => trim((string) $name),
            'email' => strtolower(trim((string) $email)),
            'password' => $password,
            'role' => User::ROLE_SUPERADMIN,
            'status' => User::STATUS_APPROVED,
            'approved_at' => now(),
            'is_active' => true,
        ]);

        $this->info("Superadmin created: {$superadmin->email} (ID: {$superadmin->id})");

        return self::SUCCESS;
    }
}
