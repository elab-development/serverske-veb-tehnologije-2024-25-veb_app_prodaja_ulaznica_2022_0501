<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->foreign('event_id')
                ->references('id')->on('events')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('event_id')
                ->references('id')->on('events')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('ticket_type_id')
                ->references('id')->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['ticket_type_id']);
            $table->dropForeign(['event_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });
    }
};
