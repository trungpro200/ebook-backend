<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $email = 'standard-ebooks-import@mocthu.invalid';
        $existingOwner = DB::table('users')->where('email', $email)->first();

        if ($existingOwner !== null) {
            if ($existingOwner->role !== 'admin') {
                throw new RuntimeException('The Standard Ebooks import owner email belongs to a non-admin user.');
            }

            return;
        }

        DB::table('users')->insert([
            'name' => 'Standard Ebooks Import',
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Preserve the account on rollback because imported books may reference it.
     */
    public function down(): void {}
};
