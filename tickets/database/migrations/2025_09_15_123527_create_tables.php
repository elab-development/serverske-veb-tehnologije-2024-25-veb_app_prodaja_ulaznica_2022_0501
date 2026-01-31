<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // events
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('venue');
            $table->string('city')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->timestamps();
        });

        // ticket_types
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // purchases
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired']);
            $table->dateTime('reserved_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('events');
    }
};
