<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        $admin = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $admin->roles()->create([
            'name' => 'super-admin',
        ]);

        $admin->save();

        $users = User::where('is_active', false)->get();

        foreach ($users as $user) {
            $user->status = 'archived';
            $user->save();
            $user->tokens()->delete();
        }

        $setting = new Setting();
        $setting->key = 'maintenance_mode';
        $setting->value = 'false';
        $setting->save();

        $oldUsers = User::where('created_at', '<', '2020-01-01')->get();
        foreach ($oldUsers as $oldUser) {
            $oldUser->delete();
        }
    }

    public function down(): void
    {
        // Cannot reverse Eloquent operations
    }
};
