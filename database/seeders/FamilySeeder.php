<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Legt die Familienkonten aus _project-assets/family.json an:
 * [{"name": "Markus", "email": "markus@freise.de", "password": "…", "is_admin": true}, …]
 * Ohne Datei nur Markus mit Zufallspasswort. Bestehende Konten bleiben unverändert.
 */
class FamilySeeder extends Seeder
{
    public function run(): void
    {
        $file = base_path('_project-assets/family.json');
        $people = is_file($file)
            ? (json_decode((string) file_get_contents($file), true) ?: [])
            : [['name' => 'Markus', 'email' => 'markus@freise.de', 'is_admin' => true]];

        foreach ($people as $person) {
            $email = strtolower($person['email']);
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $password = $person['password'] ?? Str::password(14, symbols: false);
            User::create([
                'name' => $person['name'],
                'email' => $email,
                'password' => $password,
                'is_admin' => (bool) ($person['is_admin'] ?? false),
            ]);
            $this->command?->line("Konto {$email} angelegt, Passwort: {$password}");
        }
    }
}
