<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'al-amal'],
            [
                'name' => 'Al Amal School',
                'email' => 'contact@al-amal.school',
                'phone' => '+1234567890',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@al-amal.school'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'school_id' => $school->id,
            ]
        );
    }
}
