<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('courier_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('speed', 8, 3)->nullable();

            $table->timestamp('recorded_at');

            $table->timestamps();

            $table->index(['order_id', 'recorded_at']);
            $table->index(['courier_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_locations');
    }
};

