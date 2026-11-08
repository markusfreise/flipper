<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('user:create {name} {email} {--password= : Ohne Angabe wird ein Zufallspasswort erzeugt} {--admin}')]
#[Description('Familienkonto anlegen')]
class UserCreate extends Command
{
    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        if (User::where('email', $email)->exists()) {
            $this->error("Konto {$email} gibt es schon. Passwort ändern: php artisan user:password {$email}");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);
        User::create([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'is_admin' => (bool) $this->option('admin'),
        ]);

        $this->info("Konto angelegt: {$email}");
        $this->line("Passwort: {$password}");

        return self::SUCCESS;
    }
}
