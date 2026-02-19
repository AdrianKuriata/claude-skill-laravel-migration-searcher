<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->integer('stock')->unsigned();
            $table->foreignId('category_id');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->json('metadata')->nullable();
            $table->index('name');
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('product_id');
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('sku')->unique();
            $table->decimal('price_modifier', 8, 2)->default(0);
            $table->timestamps();
        });

        DB::table('categories')->insert([
            'name' => 'Default',
            'slug' => 'default',
        ]);

        DB::table('settings')
            ->where('key', 'catalog_version')
            ->update([
                'value' => '3.0',
            ]);

        DB::table('old_products')
            ->where('archived', true)
            ->delete();

        DB::statement('CREATE INDEX idx_products_price ON products (price)');

        $sql = <<<SQL
            UPDATE products SET search_vector = to_tsvector(name || description)
        SQL;

        DB::unprepared($sql);
    }

    public function down(): void
    {
        // Drop handled separately
    }
};
