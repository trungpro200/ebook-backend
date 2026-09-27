<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('users:set-role {email} {role : reader or admin}')]
#[Description('Assign a role to an existing user and revoke their current tokens')]
class SetUserRole extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = $this->argument('role');
        if (! in_array($role, [User::ROLE_READER, User::ROLE_ADMIN], true)) {
            $this->error('Role must be reader or admin.');

            return self::FAILURE;
        }

        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('User not found. Register the account first.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $role): void {
            $user->role = $role;
            $user->save();
            $user->tokens()->delete();
        });
        $this->info('Role updated. The user must sign in again.');

        return self::SUCCESS;
    }
}
