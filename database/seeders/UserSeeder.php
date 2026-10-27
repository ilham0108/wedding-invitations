<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccount('admin', 'Super Admin', RoleSlug::SuperAdmin);
        $this->seedAccount('demo', 'Demo Client', RoleSlug::Client);
    }

    private function seedAccount(string $key, string $name, RoleSlug $role): void
    {
        $email = config("seeding.{$key}.email");
        $password = config("seeding.{$key}.password");

        if (blank($password)) {
            $this->command?->warn("Skipped {$email}: set SEED_".strtoupper($key).'_PASSWORD in .env to seed this account.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $user->name ?? $name,
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $user->assignRole($role);
    }
}
