<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Utwórz role (jeśli jeszcze nie ma)
        foreach (['Zarzad', 'Kierownik', 'Handlowiec'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Sprawdź, czy już istnieje admin
        if (! User::where('email', 'wojtek.kibitlewski@gmail.com')->exists()) {
            $admin = User::create([
                'name'       => 'Wojtek Kibitlewski',
                'email'      => 'wojtek.kibitlewski@gmail.com',
                'password'   => Hash::make('Haslo123!'), // tymczasowe hasło
                'is_active'  => true,
                'manager_id' => null,
            ]);

            $admin->assignRole('Zarzad');

            $this->command->info('Dodano użytkownika: Wojtek Kibitlewski (Zarzad)');
        } else {
            $this->command->info('Użytkownik Wojtek Kibitlewski już istnieje — pomijam.');
        }
    }
}