<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id');
            $t->unsignedBigInteger('user_id');
            $t->enum('status', ['queued', 'admitted', 'expired'])->default('queued');
            $t->string('token', 64)->nullable();
            $t->dateTime('ttl_until')->nullable();
            $t->timestamps();

            $t->unique(['event_id', 'user_id'], 'waitlist_event_user_unique');
            $t->index(['event_id', 'status', 'id'], 'waitlist_event_status_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
