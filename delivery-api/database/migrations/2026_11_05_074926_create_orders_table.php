<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number', 30)->unique();

            $table->foreignId('customer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('courier_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('delivery_type', 20);
            $table->string('status', 30)->default('pending');

            $table->string('sender_name');
            $table->string('sender_phone', 30);

            $table->text('pickup_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();

            $table->string('recipient_name');
            $table->string('recipient_phone', 30);

            $table->text('delivery_address');
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();

            $table->dateTime('scheduled_pickup_at')->nullable();

            $table->text('notes')->nullable();
            $table->decimal('price', 12, 2)->nullable();

            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['courier_id', 'status']);
            $table->index(['delivery_type', 'scheduled_pickup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};