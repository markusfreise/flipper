<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('user:password {email} {--password= : Ohne Angabe wird ein Zufallspasswort erzeugt}')]
#[Description('Passwort eines Kontos neu setzen')]
class UserPassword extends Command
{
    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('Kein Konto mit dieser E-Mail.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);
        $user->update(['password' => $password]);

        $this->info("Neues Passwort für {$user->email}:");
        $this->line($password);

        return self::SUCCESS;
    }
}
