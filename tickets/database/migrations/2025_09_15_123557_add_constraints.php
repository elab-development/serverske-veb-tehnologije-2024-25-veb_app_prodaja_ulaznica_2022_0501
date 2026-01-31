<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        // ticket_types: sold <= total ; price >= 0
        if (in_array($driver, ['mysql', 'pgsql'])) {
            DB::statement('ALTER TABLE ticket_types
                ADD CONSTRAINT chk_ticket_types_qty
                CHECK (quantity_sold <= quantity_total)');

            DB::statement('ALTER TABLE ticket_types
                ADD CONSTRAINT chk_ticket_types_price_nonneg
                CHECK (price >= 0)');
        }

        // purchases: quantity > 0 ; unit_price >= 0 ; total_amount >= 0
        if (in_array($driver, ['mysql', 'pgsql'])) {
            DB::statement('ALTER TABLE purchases
                ADD CONSTRAINT chk_purchases_qty_positive
                CHECK (quantity > 0)');

            DB::statement('ALTER TABLE purchases
                ADD CONSTRAINT chk_purchases_unit_price_nonneg
                CHECK (unit_price >= 0)');

            DB::statement('ALTER TABLE purchases
                ADD CONSTRAINT chk_purchases_total_nonneg
                CHECK (total_amount >= 0)');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'])) {
            DB::statement('ALTER TABLE purchases DROP CONSTRAINT IF EXISTS chk_purchases_total_nonneg');
            DB::statement('ALTER TABLE purchases DROP CONSTRAINT IF EXISTS chk_purchases_unit_price_nonneg');
            DB::statement('ALTER TABLE purchases DROP CONSTRAINT IF EXISTS chk_purchases_qty_positive');
            DB::statement('ALTER TABLE ticket_types DROP CONSTRAINT IF EXISTS chk_ticket_types_price_nonneg');
            DB::statement('ALTER TABLE ticket_types DROP CONSTRAINT IF EXISTS chk_ticket_types_qty');
        }
    }
};
