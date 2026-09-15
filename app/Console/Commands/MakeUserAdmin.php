<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email? : The email of the user to make admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user an administrator';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!$email) {
            // If no email provided, show all users and let them choose
            $users = User::all();
            
            if ($users->isEmpty()) {
                $this->error('No users found in the database.');
                return 1;
            }

            $this->info('Available users:');
            foreach ($users as $user) {
                $adminStatus = $user->is_admin ? '[ADMIN]' : '';
                $this->line($user->email . ' ' . $adminStatus);
            }

            $email = $this->ask('Enter the email of the user to make admin');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        if ($user->is_admin) {
            $this->info("User '{$email}' is already an admin.");
            return 0;
        }

        $user->update(['is_admin' => true]);
        $this->info("User '{$email}' has been made an admin successfully.");

        return 0;
    }
}
