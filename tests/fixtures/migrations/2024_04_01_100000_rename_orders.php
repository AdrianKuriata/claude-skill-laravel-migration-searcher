<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('orders', 'customer_orders');
    }

    public function down(): void
    {
        Schema::rename('customer_orders', 'orders');
    }
};
