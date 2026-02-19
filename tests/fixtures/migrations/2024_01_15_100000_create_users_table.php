<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->foreignId('role_id')->constrained();
            $table->foreign('department_id')->references('id')->on('departments');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop handled separately
    }
};
